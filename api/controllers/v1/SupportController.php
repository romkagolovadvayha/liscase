<?php

namespace api\controllers\v1;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportFile;
use common\models\support\SupportSticker;
use common\models\support\SupportRead;
use common\models\user\User;
use common\models\statistics\Reports;
use common\models\servers\Servers;
use common\components\helpers\Role;
use common\components\queue\support\BeforeMessageJob;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с поддержкой (тикеты и сообщения)
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Support")
 */
class SupportController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для всех методов
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['options'],
        ];

        // Убеждаемся, что CORS обрабатывается первым
        if (isset($behaviors['cors'])) {
            // Перемещаем CORS в начало массива
            $cors = $behaviors['cors'];
            unset($behaviors['cors']);
            $behaviors = ['cors' => $cors] + $behaviors;
        }

        return $behaviors;
    }

    /**
     * Список тикетов
     * 
     * @OA\Get(
     *     path="/v1/support/tickets",
     *     operationId="getSupportTickets",
     *     tags={"Support"},
     *     summary="Получить список тикетов поддержки",
     *     description="Требует JWT авторизации. Обычные пользователи видят только свои тикеты.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Лимит тикетов",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список тикетов",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Поддержка заблокирована")
     * )
     */
    public function actionTickets()
    {
        $user = $this->getCurrentUser();

        // Только постоянная блокировка чата скрывает список; мут (blocked_support_at) не мешает читать тикеты.
        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->blocked_support) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        // Фильтрация по ролям
        $baseQuery = Support::find()
            ->with(['user', 'user.userProfile']);

        // Обычные пользователи видят только свои тикеты
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            $baseQuery->andWhere(['user_id' => $user->id]);
        }

        // Получаем 20 открытых тикетов
        $openTicketsQuery = (clone $baseQuery)
            ->andWhere(['status' => Support::STATUS_OPEN])
            ->orderBy(['updated_at' => SORT_DESC])
            ->limit(20);
        $openTickets = $openTicketsQuery->all();

        // Получаем 20 закрытых тикетов
        $closedTicketsQuery = (clone $baseQuery)
            ->andWhere(['status' => Support::STATUS_CLOSED])
            ->orderBy(['updated_at' => SORT_DESC])
            ->limit(20);
        $closedTickets = $closedTicketsQuery->all();

        // Объединяем тикеты (сначала открытые, потом закрытые)
        $tickets = array_merge($openTickets, $closedTickets);

        // Получаем количество непрочитанных сообщений
        $unreadMessages = SupportRead::find()
            ->select(['support_id', 'cnt' => new \yii\db\Expression('COUNT(*)')])
            ->where(['user_id' => $user->id, 'status' => SupportRead::STATUS_UNREAD])
            ->asArray()
            ->groupBy('support_id')
            ->indexBy('support_id')
            ->all();

        // Форматируем тикеты
        $formattedTickets = [];
        foreach ($tickets as $ticket) {
            $formattedTickets[] = $this->formatTicket($ticket, $unreadMessages[$ticket->id]['cnt'] ?? 0);
        }

        return $this->successResponse([
            'tickets' => $formattedTickets,
            /** Текущий пользователь JWT — для формы «новый тикет» и списка без отдельного /me */
            'viewer' => [
                'blocked_support' => (bool) $user->blocked_support,
                'blocked_support_at' => $user->blocked_support_at,
                'status' => (int) $user->status,
                'viewer_can_write' => !$user->isSupportWritingBlocked(),
            ],
        ]);
    }

    /**
     * Тикеты пользователя (для админов/модераторов)
     * 
     * @OA\Get(
     *     path="/v1/support/user-tickets",
     *     operationId="getUserTickets",
     *     tags={"Support"},
     *     summary="Получить все тикеты пользователя",
     *     description="Требует JWT авторизации. Доступно только для админов, модераторов и поддержки.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="query",
     *         required=true,
     *         description="ID пользователя",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список тикетов пользователя",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен")
     * )
     */
    public function actionUserTickets()
    {
        $user = $this->getCurrentUser();

        // Проверяем права доступа
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        // Получаем userId из query параметров
        $userId = Yii::$app->request->get('userId');
        if (!$userId) {
            throw new BadRequestHttpException('Параметр userId обязателен');
        }

        // Получаем все тикеты пользователя
        $tickets = Support::find()
            ->where(['user_id' => $userId])
            ->with(['user', 'user.userProfile'])
            ->orderBy(['updated_at' => SORT_DESC])
            ->all();

        // Форматируем тикеты
        $formattedTickets = [];
        foreach ($tickets as $ticket) {
            $formattedTickets[] = $this->formatTicket($ticket, 0);
        }

        return $this->successResponse([
            'tickets' => $formattedTickets,
        ]);
    }

    /**
     * Список стикеров поддержки
     * 
     * @OA\Get(
     *     path="/v1/support/stickers",
     *     operationId="getSupportStickers",
     *     tags={"Support"},
     *     summary="Получить список стикеров поддержки",
     *     description="Требует JWT авторизации. Доступно всем авторизованным пользователям. Возвращает только активные стикеры.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Список стикеров",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="stickers",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="smile"),
     *                         @OA\Property(property="name", type="string", example="Улыбка"),
     *                         @OA\Property(property="type", type="string", example="image"),
     *                         @OA\Property(property="url", type="string", example="https://storage.prostoj.store/support/stickers/sticker_123.jpg"),
     *                         @OA\Property(property="width", type="integer", nullable=true, example=100),
     *                         @OA\Property(property="height", type="integer", nullable=true, example=100)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен")
     * )
     */
    public function actionStickers()
    {
        // Доступно всем авторизованным пользователям
        // Используем кэширование на 1 час
        $cacheKey = 'api_support_stickers';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            // Получаем только активные стикеры
            $stickers = SupportSticker::getActive();
            
            // Форматируем стикеры
            $formattedStickers = [];
            foreach ($stickers as $sticker) {
                $formattedStickers[] = [
                    'id' => $sticker->id,
                    'code' => $sticker->code,
                    'name' => $sticker->name,
                    'type' => $sticker->type,
                    'url' => $sticker->getPublicUrl(),
                    'width' => $sticker->width,
                    'height' => $sticker->height,
                ];
            }
            
            // Кэшируем на 1 час (3600 секунд)
            Yii::$app->cache->set($cacheKey, $formattedStickers, 3600);
            
            return $this->successResponse([
                'stickers' => $formattedStickers,
            ]);
        }

        return $this->successResponse([
            'stickers' => $cached,
        ]);
    }

    /**
     * Детали тикета
     * 
     * @OA\Get(
     *     path="/v1/support/tickets/{id}",
     *     operationId="getSupportTicket",
     *     tags={"Support"},
     *     summary="Получить детали тикета и сообщения",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Детали тикета с сообщениями",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Тикет не найден")
     * )
     */
    public function actionView($id)
    {
        $user = $this->getCurrentUser();
        $user = User::findOne((int) $user->id) ?? $user;
        // Просмотр при муте разрешён; полная блокировка чата — нет.
        if ($user->blocked_support) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        // Сервер тикета, профиль и steam_id нужны для блока USER_INFO (как в frontend/views/support/message/_user_info.php)
        $ticket = Support::find()
            ->where(['id' => $ticket->id])
            ->with(['user', 'user.userProfile', 'user.server', 'server'])
            ->one();

        // Просмотр тикета (в т.ч. закрытого) разрешён: создателю, админу, модератору, поддержке
        $isCreator = (int) $ticket->user_id === (int) $user->id;
        $isStaff = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
        if (!$isCreator && !$isStaff) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        // Загружаем сообщения
        $messages = SupportMessage::find()
            ->where(['support_id' => $ticket->id])
            ->with(['user', 'user.userProfile', 'supportFiles'])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        // Отмечаем сообщения как прочитанные и оповещаем WebSocket (индикаторы прочтения у отправителей)
        if (!empty($messages)) {
            $markedRead = SupportRead::readedAllReturningMessageIds($ticket->id, $user->id);
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int) $user->id, $markedRead);
        }

        // Форматируем сообщения
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessage($message, $ticket, (int) $user->id);
        }

        return $this->successResponse([
            'ticket' => $this->formatTicketDetail($ticket, $user),
            'messages' => $formattedMessages,
            'reports' => $this->formatTicketOwnerReports($ticket),
        ]);
    }

    /**
     * Последние репорты, отправленные владельцем тикета (steam_id в servers_reports — автор жалобы).
     */
    protected function formatTicketOwnerReports(Support $ticket): array
    {
        $owner = $ticket->user;
        if (!$owner || $owner->steam_id === null || $owner->steam_id === '') {
            return [];
        }

        $rows = Reports::find()
            ->with('user')
            ->andWhere(['steam_id' => $owner->steam_id])
            ->orderBy(['id' => SORT_DESC])
            ->limit(3)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            if (empty($row->user)) {
                continue;
            }
            $u = $row->user;
            $out[] = [
                'id' => (int) $row->id,
                'user' => [
                    'id' => (int) $u->id,
                    'username' => $u->username,
                    'steam_id' => $u->steam_id,
                    'avatar' => $u->getAvatar(),
                ],
                'reason' => (string) $row->reason,
                'created_at' => $row->created_at,
            ];
        }

        return $out;
    }

    /**
     * Создание тикета
     * 
     * @OA\Post(
     *     path="/v1/support/tickets/create",
     *     operationId="createSupportTicket",
     *     tags={"Support"},
     *     summary="Создать новый тикет поддержки",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"message"},
     *                 @OA\Property(property="message", type="string", example="Текст сообщения", description="Текст первого сообщения"),
     *                 @OA\Property(property="suspect_user_id", type="integer", example=123, description="ID пользователя, на которого подается жалоба"),
     *                 @OA\Property(property="server_tag", type="string", example="rust1", description="Тег сервера")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Тикет создан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Поддержка заблокирована")
     * )
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();
        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->isSupportWritingBlocked()) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $message = (string) ($this->getSupportRequestParam('message') ?? '');
        // Получаем файлы - пробуем оба варианта имени (files[] и files)
        $files = UploadedFile::getInstancesByName('files[]');
        if (empty($files)) {
            $files = UploadedFile::getInstancesByName('files');
        }
        
        // Проверяем, есть ли стикеры в сообщении (тег <img class="support_sticker")
        $hasStickers = !empty($message) && (
            strpos($message, 'class="support_sticker"') !== false || 
            strpos($message, "class='support_sticker'") !== false
        );
        
        // Проверяем, что есть либо сообщение (включая стикеры), либо файлы
        if (empty($message) && empty($files)) {
            return $this->errorResponse('INVALID_REQUEST', 'Message, files, or sticker is required', [], 400);
        }

        $suspectRaw = $this->getSupportRequestParam('suspect_user_id');
        $suspectUserId = ($suspectRaw !== null && $suspectRaw !== '') ? (int) $suspectRaw : null;
        $serverTagRaw = $this->getSupportRequestParam('server_tag');
        $serverTag = ($serverTagRaw !== null && $serverTagRaw !== '') ? (string) $serverTagRaw : null;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем тикет
            $ticket = new Support();
            $ticket->user_id = $user->id;
            $ticket->suspect_user_id = $suspectUserId ? (int)$suspectUserId : null;
            $ticket->server_tag = $serverTag;
            $ticket->status = Support::STATUS_OPEN;
            $ticket->created_at = date('Y-m-d H:i:s');
            $ticket->updated_at = date('Y-m-d H:i:s');

            if (!$ticket->save()) {
                $transaction->rollBack();
                return $this->validationErrorResponse($ticket);
            }

            // Добавляем системное сообщение с информацией о пользователе
            $systemMessage = new SupportMessage();
            $systemMessage->user_id = null;
            $systemMessage->message = "{USER_INFO}";
            $systemMessage->support_id = $ticket->id;
            $systemMessage->created_at = date('Y-m-d H:i:s');
            $systemMessage->save(false);

            // Добавляем системное сообщение с предупреждением
            $alertMessage = new SupportMessage();
            $alertMessage->user_id = null;
            $alertMessage->message = "{ALERT_REPORT}";
            $alertMessage->support_id = $ticket->id;
            $alertMessage->created_at = date('Y-m-d H:i:s');
            $alertMessage->save(false);

            // Добавляем пользовательское сообщение
            $userMessage = new SupportMessage();
            $userMessage->user_id = $user->id;
            $userMessage->message = $message ?: null;
            $userMessage->support_id = $ticket->id;
            $userMessage->created_at = date('Y-m-d H:i:s');
            $userMessage->save(false);

            // Обрабатываем файлы, если они есть
            if (!empty($files)) {
                // Валидация типов файлов
                $allowedMimeTypes = [
                    // Изображения
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
                    // Видео
                    'video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/ogg',
                    // Архивы
                    'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed',
                    'application/x-tar', 'application/gzip', 'application/x-gzip',
                    // .map файлы
                    'application/json', 'text/plain',
                ];
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'mp4', 'avi', 'mov', 'webm', 'ogg',
                                     'zip', 'rar', '7z', 'tar', 'gz', 'map', 'json', 'txt'];
                
                foreach ($files as $file) {
                    $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
                    if (!in_array($file->type, $allowedMimeTypes) && !in_array($extension, $allowedExtensions)) {
                        $transaction->rollBack();
                        return $this->errorResponse('INVALID_FILE_TYPE', 'Недопустимый тип файла: ' . $file->name, [], 400);
                    }
                    
                    // Проверка размера файла (максимум 100 МБ)
                    if ($file->size > 100 * 1024 * 1024) {
                        $transaction->rollBack();
                        return $this->errorResponse('FILE_TOO_LARGE', 'Файл слишком большой: ' . $file->name . ' (максимум 100 МБ)', [], 400);
                    }
                }

                // Загружаем файлы в S3
                $s3Api = Yii::$app->s3Api;
                foreach ($files as $file) {
                    try {
                        // Генерируем уникальное имя файла
                        $extension = pathinfo($file->name, PATHINFO_EXTENSION);
                        $fileName = uniqid('support_', true) . '_' . time() . '.' . $extension;
                        $s3Key = 'support/' . $fileName;
                        
                        // Загружаем файл в S3
                        $uploaded = $s3Api->putFile($s3Key, $file->tempName, $file->type);
                        
                        if ($uploaded) {
                            // Сохраняем информацию о файле в БД
                            $supportFile = new SupportFile();
                            $supportFile->support_message_id = $userMessage->id;
                            $supportFile->file = $fileName;
                            $supportFile->filename = $file->name;
                            $supportFile->mimetype = $file->type;
                            $supportFile->created_at = date('Y-m-d H:i:s');
                            $supportFile->save(false);
                        } else {
                            throw new \Exception('Failed to upload file to S3');
                        }
                    } catch (\Exception $e) {
                        Yii::error('Failed to upload file: ' . $e->getMessage());
                        $transaction->rollBack();
                        return $this->errorResponse('FILE_UPLOAD_FAILED', 'Ошибка загрузки файла: ' . $file->name, [], 500);
                    }
                }
            }

            $transaction->commit();

            $ticketNumber = $ticket->getNumber();

            // Telegram канал поддержки (как ChatServer / SupportGameStoresController)
            $this->notifySupportTelegramNewMessage($ticket, $userMessage, $user, !empty($files));

            // WebSocket + Next.js push: новый тикет и первые сообщения в чате
            try {
                \console\controllers\NotificationServer::broadcastNewTicket($ticketNumber, $user->id);
                foreach ([$systemMessage, $alertMessage, $userMessage] as $msg) {
                    \console\controllers\NotificationServer::broadcastNewSupportMessage(
                        $ticketNumber,
                        $msg->id,
                        $msg->user_id !== null ? (int) $msg->user_id : null,
                        (int) $ticket->user_id
                    );
                }
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            Yii::$app->response->statusCode = 201;
            return $this->successResponse([
                'ticket' => $this->formatTicketDetail($ticket, $user),
            ]);

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Failed to create ticket: ' . $e->getMessage());
            return $this->errorResponse('CREATION_FAILED', 'Failed to create ticket', [], 500);
        }
    }

    /**
     * Отправка сообщения в тикет
     * 
     * @OA\Post(
     *     path="/v1/support/tickets/{id}/messages",
     *     operationId="sendSupportMessage",
     *     tags={"Support"},
     *     summary="Отправить сообщение в тикет",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"message"},
     *                 @OA\Property(property="message", type="string", example="Текст сообщения")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Сообщение отправлено",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Тикет закрыт или неверные параметры"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Тикет не найден")
     * )
     */
    public function actionSend($id)
    {
        $user = $this->getCurrentUser();
        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->isSupportWritingBlocked()) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        // Проверка доступа
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]) 
            && $ticket->user_id !== $user->id) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        // Запрещаем отправку сообщений в закрытые тикеты для всех пользователей
        if ($ticket->status === Support::STATUS_CLOSED) {
            return $this->errorResponse('TICKET_CLOSED', 'Тикет закрыт. Нельзя отправлять сообщения в закрытые тикеты', [], 400);
        }

        $message = (string) ($this->getSupportRequestParam('message') ?? '');
        // Получаем файлы - пробуем оба варианта имени (files[] и files)
        $files = UploadedFile::getInstancesByName('files[]');
        if (empty($files)) {
            $files = UploadedFile::getInstancesByName('files');
        }
        
        // Проверяем, что есть либо сообщение, либо файлы
        if (empty($message) && empty($files)) {
            return $this->errorResponse('INVALID_REQUEST', 'Message or files are required', [], 400);
        }

        // Валидация типов файлов
        $allowedMimeTypes = [
            // Изображения
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
            // Видео
            'video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/ogg',
            // Архивы
            'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip', 'application/x-gzip',
            // .map файлы
            'application/json', 'text/plain',
        ];
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'mp4', 'avi', 'mov', 'webm', 'ogg',
                             'zip', 'rar', '7z', 'tar', 'gz', 'map', 'json', 'txt'];
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
            if (!in_array($file->type, $allowedMimeTypes) && !in_array($extension, $allowedExtensions)) {
                return $this->errorResponse('INVALID_FILE_TYPE', 'Недопустимый тип файла: ' . $file->name, [], 400);
            }
            
            // Проверка размера файла (максимум 100 МБ)
            if ($file->size > 100 * 1024 * 1024) {
                return $this->errorResponse('FILE_TOO_LARGE', 'Файл слишком большой: ' . $file->name . ' (максимум 100 МБ)', [], 400);
            }
        }

        try {
            $supportMessage = new SupportMessage();
            $supportMessage->user_id = $user->id;
            $supportMessage->message = $message ?: null;
            $supportMessage->support_id = $ticket->id;
            $supportMessage->created_at = date('Y-m-d H:i:s');

            if (!$supportMessage->save()) {
                return $this->validationErrorResponse($supportMessage);
            }

            // Загружаем файлы в S3
            if (!empty($files)) {
                $s3Api = Yii::$app->s3Api;
                foreach ($files as $file) {
                    try {
                        // Генерируем уникальное имя файла
                        $extension = pathinfo($file->name, PATHINFO_EXTENSION);
                        $fileName = uniqid('support_', true) . '_' . time() . '.' . $extension;
                        $s3Key = 'support/' . $fileName;
                        
                        // Загружаем файл в S3
                        $uploaded = $s3Api->putFile($s3Key, $file->tempName, $file->type);
                        
                        if ($uploaded) {
                            // Сохраняем информацию о файле в БД
                            $supportFile = new SupportFile();
                            $supportFile->support_message_id = $supportMessage->id;
                            $supportFile->file = $fileName;
                            $supportFile->filename = $file->name;
                            $supportFile->mimetype = $file->type;
                            $supportFile->created_at = date('Y-m-d H:i:s');
                            $supportFile->save(false);
                        }
                    } catch (\Exception $e) {
                        Yii::error('Failed to upload file: ' . $e->getMessage() . ', file: ' . $file->name);
                        // Продолжаем обработку других файлов
                    }
                }
            }

            // Обновляем время обновления тикета
            $ticket->updated_at = date('Y-m-d H:i:s');
            $ticket->save(false);

            // Отмечаем входящие как прочитанные для отправителя и рассылаем read-receipt по WS
            $markedRead = SupportRead::readedAllReturningMessageIds($ticket->id, $user->id);
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int) $user->id, $markedRead);
            // Создаем непрочитанные записи для получателей (владелец + staff, кроме отправителя).
            SupportRead::createRecord((int) $ticket->user_id, (int) $user->id, (int) $supportMessage->id, (int) $ticket->id);

            // Отправляем уведомления через WebSocket (если настроено)
            try {
                $ticketNumber = $ticket->getNumber();
                Yii::info("Calling broadcastNewSupportMessage: ticketNumber={$ticketNumber}, messageId={$supportMessage->id}, userId={$user->id}, ownerUserId={$ticket->user_id}");
                \console\controllers\NotificationServer::broadcastNewSupportMessage($ticketNumber, $supportMessage->id, $user->id, $ticket->user_id);
                Yii::info("broadcastNewSupportMessage called successfully");
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
                Yii::error('WebSocket broadcast exception: ' . $ex->getTraceAsString());
            }

            $this->notifySupportTelegramNewMessage($ticket, $supportMessage, $user, !empty($files));

            Yii::$app->response->statusCode = 201;
            return $this->successResponse([
                'message' => $this->formatMessage($supportMessage, $ticket, (int) $user->id),
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to send message: ' . $e->getMessage());
            return $this->errorResponse('SEND_FAILED', 'Failed to send message', [], 500);
        }
    }

    /**
     * Закрытие тикета
     * 
     * @OA\Post(
     *     path="/v1/support/tickets/{id}/close",
     *     operationId="closeSupportTicket",
     *     tags={"Support"},
     *     summary="Закрыть тикет",
     *     description="Требует JWT авторизации. Могут закрывать только автор тикета или администратор.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Тикет закрыт",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Тикет уже закрыт"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Тикет не найден")
     * )
     */
    public function actionClose($id)
    {
        $user = $this->getCurrentUser();

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        // Закрыть может автор тикета или админ/модератор/поддержка
        $isCreator = (int) $ticket->user_id === (int) $user->id;
        $isStaff = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
        if (!$isCreator && !$isStaff) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        if ($ticket->status === Support::STATUS_CLOSED) {
            return $this->errorResponse('ALREADY_CLOSED', 'Тикет уже закрыт', [], 400);
        }

        try {
            // Добавляем системное сообщение
            $systemMessage = new SupportMessage();
            $systemMessage->user_id = null;
            $systemMessage->message = "Тикет закрыт пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>";
            $systemMessage->support_id = $ticket->id;
            $systemMessage->created_at = date('Y-m-d H:i:s');
            $systemMessage->save(false);

            $ticket->status = Support::STATUS_CLOSED;
            $ticket->save(false);

            $markedRead = SupportRead::readedAllReturningMessageIds($ticket->id, null);
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int) $user->id, $markedRead);

            // Отправляем уведомления через WebSocket
            try {
                $statusString = $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed';
                \console\controllers\NotificationServer::broadcastTicketStatus($ticket->getNumber(), $statusString);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'ticket' => $this->formatTicketDetail($ticket, $user),
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to close ticket: ' . $e->getMessage());
            return $this->errorResponse('CLOSE_FAILED', 'Failed to close ticket', [], 500);
        }
    }

    /**
     * Редактирование сообщения (только для админов/модераторов/поддержки)
     * 
     * @OA\Patch(
     *     path="/v1/support/tickets/{id}/messages/{messageId}",
     *     operationId="updateSupportMessage",
     *     tags={"Support"},
     *     summary="Редактировать сообщение в тикете",
     *     description="Требует JWT авторизации. Только администраторы, модераторы и поддержка могут редактировать сообщения.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         required=true,
     *         description="ID сообщения",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", description="Новый текст сообщения")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Сообщение отредактировано",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен (только для админов)"),
     *     @OA\Response(response=404, description="Тикет или сообщение не найдено")
     * )
     */
    public function actionUpdateMessage($id, $messageId)
    {
        // Если это DELETE запрос, вызываем логику удаления
        if (Yii::$app->request->getMethod() === 'DELETE') {
            return $this->actionDeleteMessage($id, $messageId);
        }

        $user = $this->getCurrentUser();

        // Только админы/модераторы/поддержка могут редактировать сообщения
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Доступ запрещен. Только администраторы могут редактировать сообщения.');
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        $message = SupportMessage::findOne([
            'id' => $messageId,
            'support_id' => $ticket->id,
        ]);

        if (!$message) {
            throw new NotFoundHttpException('Сообщение не найдено');
        }

        // Нельзя редактировать системные сообщения
        if ($message->user_id === null) {
            return $this->errorResponse('CANNOT_EDIT_SYSTEM', 'Нельзя редактировать системные сообщения', [], 400);
        }

        // Проверяем, есть ли файлы у сообщения
        $hasFiles = SupportFile::find()->where(['support_message_id' => $message->id])->exists();
        if ($hasFiles) {
            return $this->errorResponse('CANNOT_EDIT_WITH_FILES', 'Нельзя редактировать сообщения с файлами', [], 400);
        }

        // Проверяем, есть ли стикеры в сообщении (тег <img class="support_sticker")
        $hasStickers = strpos($message->message, 'class="support_sticker"') !== false || 
                       strpos($message->message, "class='support_sticker'") !== false;
        if ($hasStickers) {
            return $this->errorResponse('CANNOT_EDIT_WITH_STICKERS', 'Нельзя редактировать сообщения со стикерами', [], 400);
        }

        // Получаем текст сообщения из body параметров (для JSON запросов)
        $newMessageText = Yii::$app->request->getBodyParam('message', '');
        if (empty($newMessageText)) {
            // Если не найдено в body, пытаемся получить из POST (для form-data)
            $newMessageText = Yii::$app->request->post('message', '');
        }
        
        if (empty($newMessageText)) {
            return $this->errorResponse('INVALID_REQUEST', 'Message is required', [], 400);
        }

        try {
            $message->message = $newMessageText;
            if (!$message->save()) {
                return $this->validationErrorResponse($message);
            }

            // Отправляем уведомления через WebSocket
            try {
                $ticketNumber = $ticket->getNumber();
                \console\controllers\NotificationServer::broadcastMessageUpdate($ticketNumber, $message->id);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'message' => $this->formatMessage($message, $ticket, (int) $user->id),
            ]);
        } catch (\Exception $e) {
            Yii::error('Error updating message: ' . $e->getMessage());
            return $this->errorResponse('UPDATE_ERROR', 'Ошибка при редактировании сообщения: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Удаление сообщения (только для админов/модераторов/поддержки)
     * 
     * @OA\Delete(
     *     path="/v1/support/tickets/{id}/messages/{messageId}",
     *     operationId="deleteSupportMessage",
     *     tags={"Support"},
     *     summary="Удалить сообщение в тикете",
     *     description="Требует JWT авторизации. Только администраторы, модераторы и поддержка могут удалять сообщения.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Parameter(
     *         name="messageId",
     *         in="path",
     *         required=true,
     *         description="ID сообщения",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Сообщение удалено",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен (только для админов)"),
     *     @OA\Response(response=404, description="Тикет или сообщение не найдено")
     * )
     */
    public function actionDeleteMessage($id, $messageId)
    {
        $user = $this->getCurrentUser();

        // Только админы/модераторы/поддержка могут удалять сообщения
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Доступ запрещен. Только администраторы могут удалять сообщения.');
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        $message = SupportMessage::findOne([
            'id' => $messageId,
            'support_id' => $ticket->id,
        ]);

        if (!$message) {
            throw new NotFoundHttpException('Сообщение не найдено');
        }

        try {
            // Получаем файлы сообщения
            $files = SupportFile::findAll(['support_message_id' => $message->id]);
            
            // Удаляем файлы сообщения из S3
            if (!empty($files)) {
                $s3Api = Yii::$app->s3Api;
                foreach ($files as $file) {
                    try {
                        $s3Key = 'support/' . $file->file;
                        $s3Api->deleteFile($s3Key);
                        // Удаляем запись о файле из БД
                        $file->delete();
                    } catch (\Exception $e) {
                        Yii::warning('Failed to delete file from S3: ' . $e->getMessage());
                    }
                }
            }

            // Удаляем связанные записи о прочтении
            \common\models\support\SupportRead::deleteAll(['support_message_id' => $message->id]);
            
            // Удаляем сообщение
            $deleted = $message->delete();
            if ($deleted === false || $deleted === 0) {
                Yii::error('Failed to delete message: deleted=' . var_export($deleted, true));
                return $this->errorResponse('DELETE_FAILED', 'Не удалось удалить сообщение', [], 500);
            }

            // Отправляем уведомления через WebSocket
            try {
                $ticketNumber = $ticket->getNumber();
                Yii::info("Calling broadcastMessageDelete: ticketNumber={$ticketNumber}, messageId={$messageId}");
                \console\controllers\NotificationServer::broadcastMessageDelete($ticketNumber, $messageId);
                Yii::info("broadcastMessageDelete called successfully");
            } catch (\Exception $ex) {
                Yii::error('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'message' => 'Сообщение успешно удалено',
            ]);
        } catch (\Exception $e) {
            Yii::error('Error deleting message: ' . $e->getMessage());
            return $this->errorResponse('DELETE_ERROR', 'Ошибка при удалении сообщения: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Открытие тикета
     * 
     * @OA\Post(
     *     path="/v1/support/tickets/{id}/open",
     *     operationId="openSupportTicket",
     *     tags={"Support"},
     *     summary="Открыть закрытый тикет",
     *     description="Требует JWT авторизации. Могут открывать автор тикета, администраторы, модераторы и поддержка.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Номер тикета",
     *         @OA\Schema(type="integer", example=12345)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Тикет открыт",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Тикет уже открыт"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Тикет не найден")
     * )
     */
    public function actionOpen($id)
    {
        $user = $this->getCurrentUser();

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            throw new NotFoundHttpException('Тикет не найден');
        }

        // Проверка доступа: владелец тикета или админ/модератор/поддержка могут открывать
        $isOwner = $ticket->user_id === $user->id;
        $isAdminOrModerator = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
        
        if (!$isOwner && !$isAdminOrModerator) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        if ($ticket->status === Support::STATUS_OPEN) {
            return $this->errorResponse('ALREADY_OPEN', 'Тикет уже открыт', [], 400);
        }

        try {
            // Добавляем системное сообщение
            $systemMessage = new SupportMessage();
            $systemMessage->user_id = null;
            $systemMessage->message = "Тикет открыт пользователем <span class=\"support_messages_item_message_success\">{$user->username}</span>";
            $systemMessage->support_id = $ticket->id;
            $systemMessage->created_at = date('Y-m-d H:i:s');
            $systemMessage->save(false);

            $ticket->status = Support::STATUS_OPEN;
            $ticket->save(false);

            // Отправляем уведомления через WebSocket
            try {
                $statusString = $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed';
                \console\controllers\NotificationServer::broadcastTicketStatus($ticket->getNumber(), $statusString);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'ticket' => $this->formatTicketDetail($ticket, $user),
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to open ticket: ' . $e->getMessage());
            return $this->errorResponse('OPEN_FAILED', 'Failed to open ticket', [], 500);
        }
    }

    /**
     * Форматирование тикета для списка
     */
    protected function formatTicket($ticket, $unreadCount = 0)
    {
        return [
            'id' => $ticket->getNumber(),
            'number' => $ticket->getNumber(),
            'user_id' => (int) $ticket->user_id,
            'status' => $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed',
            'status_name' => Support::getStatusList()[$ticket->status] ?? null,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'username' => $ticket->user->username,
                'avatar' => $ticket->user->getAvatar(),
                'blocked_support' => (bool) $ticket->user->blocked_support,
                'blocked_support_at' => $ticket->user->blocked_support_at,
                'status' => (int) $ticket->user->status,
            ] : null,
            /** Для UI до запроса деталей тикета (бейдж сервера и т.п.) */
            'server_tag' => $ticket->server_tag,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * Форматирование тикета для детального просмотра
     *
     * @param Support $ticket
     * @param \common\models\user\User|null $viewerUser текущий пользователь JWT — для viewer_can_write
     */
    protected function formatTicketDetail($ticket, $viewerUser = null)
    {
        $userPayload = null;
        if ($ticket->user) {
            $profile = $ticket->user->userProfile;
            $userPayload = [
                'id' => $ticket->user->id,
                'username' => $ticket->user->username,
                'avatar' => $ticket->user->getAvatar(),
                'blocked_support' => (bool) $ticket->user->blocked_support,
                'blocked_support_at' => $ticket->user->blocked_support_at,
                'status' => (int) $ticket->user->status,
                'steam_id' => $ticket->user->steam_id,
                'trade_link' => ($profile && !empty($profile->trade_link)) ? $profile->trade_link : null,
            ];
        }

        // Сервер для UI — текущий сервер автора тикета (User::getCurrentServer), не support.server_tag
        $serverPayload = null;
        $serverTagOut = null;
        if ($ticket->user) {
            $current = $ticket->user->getCurrentServer();
            if ($current instanceof Servers) {
                $serverPayload = [
                    'id' => (int) $current->id,
                    'name' => Yii::t('database', $current->name),
                    'tag' => $current->tag,
                ];
                $tg = $current->tag;
                $serverTagOut = ($tg !== null && $tg !== '') ? trim((string) $tg) : null;
            } elseif (is_string($current) && trim($current) !== '') {
                $tagTrim = trim($current);
                $srv = Servers::find()->where(['tag' => $tagTrim])->limit(1)->one();
                if ($srv) {
                    $serverPayload = [
                        'id' => (int) $srv->id,
                        'name' => Yii::t('database', $srv->name),
                        'tag' => $srv->tag,
                    ];
                    $serverTagOut = trim((string) $srv->tag);
                } else {
                    $serverPayload = [
                        'id' => 1,
                        'name' => $tagTrim,
                        'tag' => $tagTrim,
                    ];
                    $serverTagOut = $tagTrim;
                }
            }
        }

        $out = [
            'id' => $ticket->getNumber(),
            'number' => $ticket->getNumber(),
            'user_id' => $ticket->user_id,
            'status' => $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed',
            'status_name' => Support::getStatusList()[$ticket->status] ?? null,
            'user' => $userPayload,
            /** Текущий сервер автора тикета (User::getCurrentServer) */
            'server' => $serverPayload,
            'server_tag' => $serverTagOut,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'unread_count' => 0, // Для нового тикета всегда 0
        ];

        if ($viewerUser !== null) {
            $out['viewer_can_write'] = !$viewerUser->isSupportWritingBlocked();
        }

        return $out;
    }

    /**
     * Мут пользователя в поддержке (временная блокировка на 30 минут)
     * 
     * @OA\Post(
     *     path="/v1/support/users/{userId}/mute",
     *     operationId="muteSupportUser",
     *     tags={"Support"},
     *     summary="Выдать мут пользователю в поддержке на 30 минут",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID пользователя",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"blocked"},
     *             @OA\Property(property="blocked", type="boolean", description="Заблокировать (true) или разблокировать (false)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Успешно"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Пользователь не найден")
     * )
     */
    public function actionMute($userId)
    {
        $user = $this->getCurrentUser();

        // Только админы и модераторы могут мутить пользователей
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        $targetUser = \common\models\user\User::findOne($userId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $blocked = Yii::$app->request->post('blocked', true);
        $blocked = filter_var($blocked, FILTER_VALIDATE_BOOLEAN);
        
        // Получаем номер тикета из запроса (если передан)
        $ticketNumber = Yii::$app->request->post('ticketNumber', null);
        $ticket = null;
        if ($ticketNumber) {
            $ticket = Support::findByNumber($ticketNumber);
        }

        try {
            $date = new \DateTime();
            if ($blocked) {
                $date->modify('+30 minute');
                $targetUser->blocked_support_at = $date->format('Y-m-d H:i:s');
            } else {
                $targetUser->blocked_support_at = null;
            }
            $targetUser->save(false);

            // Создаем системное сообщение в чате, если передан номер тикета
            if ($ticket) {
                $systemMessage = new SupportMessage();
                $systemMessage->user_id = null; // Системное сообщение
                $systemMessage->support_id = $ticket->id;
                $systemMessage->created_at = date('Y-m-d H:i:s');
                
                // Экранируем имена пользователей для безопасности
                $targetUsername = htmlspecialchars($targetUser->username, ENT_QUOTES, 'UTF-8');
                $adminUsername = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');
                
                if ($blocked) {
                    $systemMessage->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$targetUsername}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span> на 30 минут.";
                } else {
                    $systemMessage->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$targetUsername}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span>.";
                }
                
                if ($systemMessage->save()) {
                    // Отправляем уведомление о новом сообщении через WebSocket
                    try {
                        \console\controllers\NotificationServer::broadcastNewSupportMessage(
                            $ticket->getNumber(),
                            $systemMessage->id,
                            null, // user_id = null для системных сообщений
                            $ticket->user_id
                        );
                    } catch (\Exception $ex) {
                        Yii::warning('WebSocket broadcast for system message failed: ' . $ex->getMessage());
                    }
                }
            }

            // Отправляем уведомления через WebSocket
            try {
                \console\controllers\NotificationServer::broadcastUserBlocked($targetUser->id, 'mute', $blocked, $targetUser->blocked_support_at);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'user' => [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username,
                    'blocked_support_at' => $targetUser->blocked_support_at,
                ],
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to mute/unmute user: ' . $e->getMessage());
            return $this->errorResponse('MUTE_FAILED', 'Failed to mute/unmute user', [], 500);
        }
    }

    /**
     * Блокировка/разблокировка чата пользователя в поддержке (постоянная блокировка)
     * 
     * @OA\Post(
     *     path="/v1/support/users/{userId}/block-chat",
     *     operationId="blockSupportChat",
     *     tags={"Support"},
     *     summary="Заблокировать/разблокировать чат пользователя в поддержке",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID пользователя",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"blocked"},
     *             @OA\Property(property="blocked", type="boolean", description="Заблокировать (true) или разблокировать (false)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Успешно"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Пользователь не найден")
     * )
     */
    public function actionBlockChat($userId)
    {
        $user = $this->getCurrentUser();

        // Только админы и модераторы могут блокировать чат
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        $targetUser = \common\models\user\User::findOne($userId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $blocked = Yii::$app->request->post('blocked', true);
        $blocked = filter_var($blocked, FILTER_VALIDATE_BOOLEAN);
        
        // Получаем номер тикета из запроса (если передан)
        $ticketNumber = Yii::$app->request->post('ticketNumber', null);
        $ticket = null;
        if ($ticketNumber) {
            $ticket = Support::findByNumber($ticketNumber);
        }

        try {
            $targetUser->blocked_support = $blocked;
            $targetUser->save(false);

            // Создаем системное сообщение в чате, если передан номер тикета
            if ($ticket) {
                $systemMessage = new SupportMessage();
                $systemMessage->user_id = null; // Системное сообщение
                $systemMessage->support_id = $ticket->id;
                $systemMessage->created_at = date('Y-m-d H:i:s');
                
                // Экранируем имена пользователей для безопасности
                $targetUsername = htmlspecialchars($targetUser->username, ENT_QUOTES, 'UTF-8');
                $adminUsername = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');
                
                if ($blocked) {
                    $systemMessage->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$targetUsername}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span> навсегда.";
                } else {
                    $systemMessage->message = "Чат игрока <span class=\"support_messages_item_message_success\">{$targetUsername}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span>.";
                }
                
                if ($systemMessage->save()) {
                    // Отправляем уведомление о новом сообщении через WebSocket
                    try {
                        \console\controllers\NotificationServer::broadcastNewSupportMessage(
                            $ticket->getNumber(),
                            $systemMessage->id,
                            null, // user_id = null для системных сообщений
                            $ticket->user_id
                        );
                    } catch (\Exception $ex) {
                        Yii::warning('WebSocket broadcast for system message failed: ' . $ex->getMessage());
                    }
                }
            }

            // Отправляем уведомления через WebSocket
            try {
                \console\controllers\NotificationServer::broadcastUserBlocked($targetUser->id, 'chat', $blocked, null);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'user' => [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username,
                    'blocked_support' => $targetUser->blocked_support,
                ],
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to block/unblock chat: ' . $e->getMessage());
            return $this->errorResponse('BLOCK_CHAT_FAILED', 'Failed to block/unblock chat', [], 500);
        }
    }

    /**
     * Блокировка/разблокировка аккаунта пользователя
     * 
     * @OA\Post(
     *     path="/v1/support/users/{userId}/block-account",
     *     operationId="blockUserAccount",
     *     tags={"Support"},
     *     summary="Блокировать/разблокировать аккаунт пользователя",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID пользователя",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"blocked"},
     *             @OA\Property(property="blocked", type="boolean", description="Заблокировать (true) или разблокировать (false)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Успешно"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен"),
     *     @OA\Response(response=404, description="Пользователь не найден")
     * )
     */
    public function actionBlockAccount($userId)
    {
        $user = $this->getCurrentUser();

        // Только админы и модераторы могут блокировать аккаунты
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
            throw new ForbiddenHttpException('Доступ запрещен');
        }

        $targetUser = \common\models\user\User::findOne($userId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $blocked = Yii::$app->request->post('blocked', true);
        $blocked = filter_var($blocked, FILTER_VALIDATE_BOOLEAN);
        
        // Получаем номер тикета из запроса (если передан)
        $ticketNumber = Yii::$app->request->post('ticketNumber', null);
        $ticket = null;
        if ($ticketNumber) {
            $ticket = Support::findByNumber($ticketNumber);
        }

        try {
            $targetUser->status = $blocked ? \common\models\user\User::STATUS_BLOCKED : \common\models\user\User::STATUS_ACTIVE;
            $targetUser->save(false);

            // Создаем системное сообщение в чате, если передан номер тикета
            if ($ticket) {
                $systemMessage = new SupportMessage();
                $systemMessage->user_id = null; // Системное сообщение
                $systemMessage->support_id = $ticket->id;
                $systemMessage->created_at = date('Y-m-d H:i:s');
                
                // Экранируем имена пользователей для безопасности
                $targetUsername = htmlspecialchars($targetUser->username, ENT_QUOTES, 'UTF-8');
                $adminUsername = htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8');
                
                if ($blocked) {
                    $systemMessage->message = "Игрок <span class=\"support_messages_item_message_success\">{$targetUsername}</span> заблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span> навсегда.";
                } else {
                    $systemMessage->message = "Игрок <span class=\"support_messages_item_message_success\">{$targetUsername}</span> разблокирован пользователем <span class=\"support_messages_item_message_success\">{$adminUsername}</span>.";
                }
                
                if ($systemMessage->save()) {
                    // Отправляем уведомление о новом сообщении через WebSocket
                    try {
                        \console\controllers\NotificationServer::broadcastNewSupportMessage(
                            $ticket->getNumber(),
                            $systemMessage->id,
                            null, // user_id = null для системных сообщений
                            $ticket->user_id
                        );
                    } catch (\Exception $ex) {
                        Yii::warning('WebSocket broadcast for system message failed: ' . $ex->getMessage());
                    }
                }
            }

            // Отправляем уведомления через WebSocket
            try {
                \console\controllers\NotificationServer::broadcastUserBlocked($targetUser->id, 'account', $blocked, null);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            return $this->successResponse([
                'user' => [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username,
                    'status' => $targetUser->status,
                ],
            ]);

        } catch (\Exception $e) {
            Yii::error('Failed to block/unblock account: ' . $e->getMessage());
            return $this->errorResponse('BLOCK_ACCOUNT_FAILED', 'Failed to block/unblock account', [], 500);
        }
    }

    /**
     * Поле из JSON-тела (application/json) или из POST — фронт Next.js шлёт JSON.
     */
    protected function getSupportRequestParam(string $name)
    {
        $v = Yii::$app->request->getBodyParam($name);
        if ($v === null) {
            $v = Yii::$app->request->post($name);
        }
        return $v;
    }

    /**
     * Публикация в Telegram-канал поддержки через очередь (как legacy ChatServer).
     */
    protected function notifySupportTelegramNewMessage(Support $ticket, SupportMessage $supportMessage, User $sender, bool $hadFiles = false): void
    {
        if (!Yii::$app->has('queueProcess')) {
            return;
        }
        $text = (string) ($supportMessage->message ?? '');
        if ($text === '' || trim(strip_tags(str_replace(['&nbsp;', "\xc2\xa0"], ' ', $text))) === '') {
            $text = $hadFiles ? '[вложения]' : '[сообщение]';
        }
        try {
            Yii::$app->queueProcess->push(new BeforeMessageJob([
                'chatId' => $supportMessage->support_id,
                'userId' => $supportMessage->user_id,
                'message' => $text,
                'username' => $sender->username,
                'chatNumber' => $ticket->getNumber(),
            ]));
        } catch (\Exception $ex) {
            Yii::warning('BeforeMessageJob: ' . $ex->getMessage());
        }
    }

    /**
     * Форматирование сообщения
     *
     * @param SupportMessage $message
     * @param Support|null $ticket
     * @param int|null $viewerUserId текущий пользователь (для is_read на своих сообщениях)
     */
    protected function formatMessage($message, $ticket = null, $viewerUserId = null)
    {
        $files = [];
        foreach ($message->supportFiles as $file) {
            $files[] = [
                'id' => $file->id,
                'filename' => $file->filename,
                'file' => $file->file,
                'mimetype' => $file->mimetype,
                'url' => $file->getPublicUrl(),
            ];
        }

        $data = [
            'id' => $message->id,
            'support_id' => $message->support_id,
            'user_id' => $message->user_id, // Добавляем user_id для проверки в frontend
            'message' => $message->message,
            'user' => $message->user ? [
                'id' => $message->user->id,
                'username' => $message->user->username,
                'avatar' => $message->user->getAvatar(),
                'avatar_frame_url' => $message->user->getAvatarFrameImageUrl(),
                'steam_id' => $message->user->steam_id,
            ] : null,
            'files' => $files,
            'created_at' => $message->created_at,
        ];

        if (
            $ticket !== null
            && $viewerUserId !== null
            && $message->user_id !== null
            && (int) $message->user_id === (int) $viewerUserId
        ) {
            $data['is_read'] = SupportRead::isOutgoingRead(
                (int) $message->id,
                (int) $message->user_id,
                (int) $ticket->user_id
            );
        }

        return $data;
    }
}


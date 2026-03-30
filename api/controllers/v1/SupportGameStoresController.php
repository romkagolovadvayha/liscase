<?php

namespace api\controllers\v1;

use common\components\queue\support\BeforeMessageJob;
use common\models\servers\Servers;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use Yii;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;

/**
 * Контроллер для работы с поддержкой через GameStoresRUST плагин
 * Авторизация: по steam_id из параметров запроса
 */
class SupportGameStoresController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Убираем JWT авторизацию, используем авторизацию по steam_id
        // CORS и ContentNegotiator уже настроены в BaseApiController

        return $behaviors;
    }

    /**
     * Получить пользователя по steam_id из параметров запроса
     * 
     * @param array $bodyParams Параметры из body запроса
     * @param Servers|null $server Сервер, с которого вызван метод (для обновления server_id пользователя)
     * @return \common\models\user\User
     * @throws UnauthorizedHttpException
     */
    protected function getUserBySteamId($bodyParams = [], $server = null)
    {
        // Пробуем получить steam_id из разных источников
        $steamId = null;
        
        // Из body параметров
        if (!empty($bodyParams['steamId'])) {
            $steamId = $bodyParams['steamId'];
        } elseif (!empty($bodyParams['steam_id'])) {
            $steamId = $bodyParams['steam_id'];
        }
        
        // Из POST параметров
        if (empty($steamId)) {
            $steamId = Yii::$app->request->post('steamId') ?: Yii::$app->request->post('steam_id');
        }
        
        // Из GET параметров
        if (empty($steamId)) {
            $steamId = Yii::$app->request->get('steamId') ?: Yii::$app->request->get('steam_id');
        }
        
        if (empty($steamId)) {
            throw new UnauthorizedHttpException('steam_id is required');
        }
        
        $steamId = (string)$steamId;
        
        // Проверка формата steam_id
        if (strlen($steamId) !== 17 || !is_numeric($steamId)) {
            throw new UnauthorizedHttpException('Invalid steam_id format');
        }
        
        /** @var \common\models\user\User $user */
        $user = \common\models\user\User::find()
            ->andWhere(['steam_id' => $steamId])
            ->one();
        
        if (empty($user)) {
            throw new UnauthorizedHttpException('User not found');
        }
        
        // Обновляем server_id пользователя, если он отличается от текущего сервера
        if ($server && (empty($user->server_id) || $user->server_id != $server->id)) {
            $oldServerId = $user->server_id;
            $user->server_id = $server->id;
            $user->server_tag = $server->tag;
            if (!$user->save(false)) {
                Yii::warning("Failed to update server_id for user {$user->id}: " . json_encode($user->getErrors()), 'support');
            } else {
                Yii::info("Updated server_id for user {$user->id} from " . ($oldServerId ?? 'null') . " to {$server->id}", 'support');
            }
        }
        
        return $user;
    }

    /**
     * Список тикетов
     * GET /v1/support-game-stores/tickets?steam_id=XXX
     * или POST /v1/support-game-stores/tickets с body: {"steamId": "XXX"}
     */
    public function actionTickets()
    {
        // Определяем сервер по IP и port из query string или headers
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');
        
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = null;
        if ($serverIp && $serverPort) {
            $server = $this->findServer($serverIp, $serverPort);
        }
        
        try {
            $bodyParams = [];
            if (Yii::$app->request->isPost) {
                $rawBody = Yii::$app->request->getRawBody();
                if (!empty($rawBody)) {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $bodyParams = $decoded;
                    }
                }
            }
            
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse('UNAUTHORIZED', $e->getMessage(), [], 401);
        }

        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->blocked_support) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        // Обычные пользователи видят только свои тикеты
        $baseQuery = Support::find()
            ->with(['user', 'user.userProfile'])
            ->andWhere(['user_id' => $user->id]);

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
        ]);
    }

    /**
     * Детали тикета с сообщениями
     * GET /v1/support-game-stores/tickets/{id}?steam_id=XXX
     * или POST /v1/support-game-stores/tickets/{id} с body: {"steamId": "XXX"}
     */
    public function actionView($id)
    {
        // Определяем сервер по IP и port из query string или headers
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');
        
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = null;
        if ($serverIp && $serverPort) {
            $server = $this->findServer($serverIp, $serverPort);
        }
        
        try {
            $bodyParams = [];
            if (Yii::$app->request->isPost) {
                $rawBody = Yii::$app->request->getRawBody();
                if (!empty($rawBody)) {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $bodyParams = $decoded;
                    }
                }
            }
            
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse('UNAUTHORIZED', $e->getMessage(), [], 401);
        }

        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->blocked_support) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            return $this->errorResponse('NOT_FOUND', 'Тикет не найден', [], 404);
        }

        // Проверка доступа (только владелец тикета)
        if ($ticket->user_id !== $user->id) {
            return $this->errorResponse('FORBIDDEN', 'Доступ запрещен', [], 403);
        }

        // Загружаем сообщения
        $messages = SupportMessage::find()
            ->where(['support_id' => $ticket->id])
            ->with(['user', 'user.userProfile', 'supportFiles', 'support', 'support.server', 'support.user'])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        // Отмечаем сообщения как прочитанные и оповещаем WebSocket
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
            'ticket' => $this->formatTicketDetail($ticket),
            'messages' => $formattedMessages,
        ]);
    }

    /**
     * Создание тикета
     * POST /v1/support-game-stores/tickets/create
     * Body: {"steamId": "XXX", "message": "текст сообщения"}
     */
    public function actionCreate()
    {
        // Определяем сервер по IP и port из query string или headers
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');
        
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = null;
        if ($serverIp && $serverPort) {
            $server = $this->findServer($serverIp, $serverPort);
        }
        
        try {
            $bodyParams = [];
            $rawBody = Yii::$app->request->getRawBody();
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $bodyParams = $decoded;
                }
            }
            
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse('UNAUTHORIZED', $e->getMessage(), [], 401);
        }

        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->isSupportWritingBlocked()) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $message = $bodyParams['message'] ?? Yii::$app->request->post('message', '');
        
        // Проверяем, что есть сообщение
        if (empty($message)) {
            return $this->errorResponse('INVALID_REQUEST', 'Message is required', [], 400);
        }

        // Определяем сервер по IP и port из query string или headers
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');
        
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = null;
        $serverTag = null;
        
        if ($serverIp && $serverPort) {
            $server = $this->findServer($serverIp, $serverPort);
            if ($server) {
                $serverTag = $server->tag;
            }
        }
        
        // Если сервер не найден по IP/port, пробуем получить server_tag из body (для обратной совместимости)
        if (empty($serverTag)) {
            $serverTag = $bodyParams['server_tag'] ?? Yii::$app->request->post('server_tag');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем тикет
            $ticket = new Support();
            $ticket->user_id = $user->id;
            $ticket->suspect_user_id = null;
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
            $userMessage->message = $message;
            $userMessage->support_id = $ticket->id;
            $userMessage->created_at = date('Y-m-d H:i:s');
            $userMessage->save(false);

            $transaction->commit();

            // Отправляем уведомление в телеграм для пользовательского сообщения (как в ChatServer)
            try {
                Yii::$app->queueProcess->push(new BeforeMessageJob([
                    'chatId' => $userMessage->support_id,
                    'userId' => $userMessage->user_id,
                    'message' => $userMessage->message,
                    'username' => $user->username,
                    'chatNumber' => $ticket->getNumber(),
                ]));
            } catch (\Exception $ex) {
                Yii::warning('Failed to push BeforeMessageJob for user message: ' . $ex->getMessage());
            }

            // Отправляем уведомления через WebSocket
            try {
                $ticketNumber = $ticket->getNumber();
                // Отправляем уведомление о новом тикете
                \console\controllers\NotificationServer::broadcastNewTicket($ticketNumber, $user->id);
                
                // Отправляем уведомления о системных сообщениях (как на сайте)
                try {
                    \console\controllers\NotificationServer::broadcastNewSupportMessage(
                        $ticketNumber,
                        $systemMessage->id,
                        null, // user_id = null для системных сообщений
                        $ticket->user_id
                    );
                } catch (\Exception $ex) {
                    Yii::warning('WebSocket broadcast for USER_INFO message failed: ' . $ex->getMessage());
                }
                
                try {
                    \console\controllers\NotificationServer::broadcastNewSupportMessage(
                        $ticketNumber,
                        $alertMessage->id,
                        null, // user_id = null для системных сообщений
                        $ticket->user_id
                    );
                } catch (\Exception $ex) {
                    Yii::warning('WebSocket broadcast for ALERT_REPORT message failed: ' . $ex->getMessage());
                }
                
                // Отправляем уведомление о пользовательском сообщении (как на сайте)
                try {
                    Yii::info("Calling broadcastNewSupportMessage: ticketNumber={$ticketNumber}, messageId={$userMessage->id}, userId={$user->id}, ownerUserId={$ticket->user_id}");
                    \console\controllers\NotificationServer::broadcastNewSupportMessage($ticketNumber, $userMessage->id, $user->id, $ticket->user_id);
                } catch (\Exception $ex) {
                    Yii::warning('WebSocket broadcast for user message failed: ' . $ex->getMessage());
                }
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

            Yii::$app->response->statusCode = 201;
            return $this->successResponse([
                'ticket' => $this->formatTicketDetail($ticket),
            ]);

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Failed to create ticket: ' . $e->getMessage());
            return $this->errorResponse('CREATION_FAILED', 'Failed to create ticket', [], 500);
        }
    }

    /**
     * Отправка сообщения в тикет
     * POST /v1/support-game-stores/tickets/{id}/messages
     * Body: {"steamId": "XXX", "message": "текст сообщения"}
     */
    public function actionSend($id)
    {
        // Определяем сервер по IP и port из query string или headers
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');
        
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = null;
        if ($serverIp && $serverPort) {
            $server = $this->findServer($serverIp, $serverPort);
        }
        
        try {
            $bodyParams = [];
            $rawBody = Yii::$app->request->getRawBody();
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $bodyParams = $decoded;
                }
            }
            
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse('UNAUTHORIZED', $e->getMessage(), [], 401);
        }

        $user = User::findOne((int) $user->id) ?? $user;
        if ($user->isSupportWritingBlocked()) {
            return $this->errorResponse('BLOCKED', 'Ваш чат поддержки заблокирован', [], 403);
        }

        $ticket = Support::findByNumber($id);
        if (!$ticket) {
            return $this->errorResponse('NOT_FOUND', 'Тикет не найден', [], 404);
        }

        // Проверка доступа (только владелец тикета)
        if ($ticket->user_id !== $user->id) {
            return $this->errorResponse('FORBIDDEN', 'Доступ запрещен', [], 403);
        }

        // Запрещаем отправку сообщений в закрытые тикеты
        if ($ticket->status === Support::STATUS_CLOSED) {
            return $this->errorResponse('TICKET_CLOSED', 'Тикет закрыт. Нельзя отправлять сообщения в закрытые тикеты', [], 400);
        }

        $message = $bodyParams['message'] ?? Yii::$app->request->post('message', '');
        
        // Проверяем, что есть сообщение
        if (empty($message)) {
            return $this->errorResponse('INVALID_REQUEST', 'Message is required', [], 400);
        }

        try {
            $supportMessage = new SupportMessage();
            $supportMessage->user_id = $user->id;
            $supportMessage->message = $message;
            $supportMessage->support_id = $ticket->id;
            $supportMessage->created_at = date('Y-m-d H:i:s');

            if (!$supportMessage->save()) {
                return $this->validationErrorResponse($supportMessage);
            }

            // Обновляем время обновления тикета
            $ticket->updated_at = date('Y-m-d H:i:s');
            $ticket->save(false);

            $markedRead = SupportRead::readedAllReturningMessageIds($ticket->id, $user->id);
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int) $user->id, $markedRead);
            // Создаем непрочитанные записи для получателей (владелец + staff, кроме отправителя).
            SupportRead::createRecord((int) $ticket->user_id, (int) $user->id, (int) $supportMessage->id, (int) $ticket->id);

            // Отправляем уведомление в телеграм (как в ChatServer)
            try {
                Yii::$app->queueProcess->push(new BeforeMessageJob([
                    'chatId' => $supportMessage->support_id,
                    'userId' => $supportMessage->user_id,
                    'message' => $supportMessage->message,
                    'username' => $user->username,
                    'chatNumber' => $ticket->getNumber(),
                ]));
            } catch (\Exception $ex) {
                Yii::warning('Failed to push BeforeMessageJob: ' . $ex->getMessage());
            }

            // Отправляем уведомления через WebSocket
            try {
                $ticketNumber = $ticket->getNumber();
                Yii::info("Calling broadcastNewSupportMessage: ticketNumber={$ticketNumber}, messageId={$supportMessage->id}, userId={$user->id}, ownerUserId={$ticket->user_id}");
                \console\controllers\NotificationServer::broadcastNewSupportMessage($ticketNumber, $supportMessage->id, $user->id, $ticket->user_id);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }

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
     * Форматирование тикета для списка
     */
    protected function formatTicket($ticket, $unreadCount = 0)
    {
        return [
            'id' => $ticket->getNumber(),
            'number' => $ticket->getNumber(),
            'status' => $ticket->status === Support::STATUS_OPEN ? 'open' : 'closed',
            'status_name' => Support::getStatusList()[$ticket->status] ?? null,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'username' => $ticket->user->username,
                'avatar' => $ticket->user->getAvatar(),
            ] : null,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * Форматирование тикета для детального просмотра
     */
    protected function formatTicketDetail($ticket)
    {
        return [
            'id' => $ticket->getNumber(),
            'number' => $ticket->getNumber(),
            'user_id' => $ticket->user_id,
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
            'server_tag' => $ticket->server_tag,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'unread_count' => 0,
        ];
    }

    /**
     * Форматирование сообщения
     *
     * @param SupportMessage $message
     * @param Support|null $ticket
     * @param int|null $viewerUserId
     */
    protected function formatMessage($message, $ticket = null, $viewerUserId = null)
    {
        $files = [];
        $hasFiles = !empty($message->supportFiles);
        
        foreach ($message->supportFiles as $file) {
            $files[] = [
                'id' => $file->id,
                'filename' => $file->filename,
                'file' => $file->file,
                'mimetype' => $file->mimetype,
                'url' => $file->getPublicUrl(),
            ];
        }

        // Заменяем плейсхолдеры на текстовые сообщения
        $formattedMessage = $message->message;
        
        // Проверяем, есть ли в сообщении стикеры (тег <img> с классом support_sticker или путь /stickers/)
        $hasSticker = false;
        if (!empty($formattedMessage)) {
            // Проверяем наличие стикера по классу support_sticker или по пути /stickers/
            if (preg_match('/class=["\']support_sticker["\']/i', $formattedMessage) || 
                preg_match('/\/stickers\/[^"\'\s>]+\.(webp|png|jpg|jpeg|gif)/i', $formattedMessage)) {
                $hasSticker = true;
            }
        }
        
        // Проверяем, есть ли в сообщении ссылки на изображения (по расширениям файлов)
        $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'ico'];
        $hasImageLink = false;
        
        if (!empty($formattedMessage) && !$hasSticker) {
            // Ищем паттерны типа *.png, *.jpg и т.д. в тексте (URL или просто расширения)
            foreach ($imageExtensions as $ext) {
                // Проверяем наличие расширения в тексте (может быть в URL или в имени файла)
                if (preg_match('/\.' . preg_quote($ext, '/') . '(\?|#|"|\'|>|<\/|$|\s)/i', $formattedMessage)) {
                    $hasImageLink = true;
                    break;
                }
            }
        }
        
        // Если сообщение содержит стикер, заменяем текст
        if ($hasSticker) {
            $formattedMessage = 'Вам отправлен стикер, чтобы увидеть зайдите на сайт';
        } elseif ($hasFiles || $hasImageLink) {
            // Если сообщение содержит файлы ИЛИ ссылку на изображение, заменяем текст
            $formattedMessage = 'Вам отправили файл, чтобы его посмотреть зайдите в раздел поддержки на сайте';
        } elseif ($formattedMessage === '{USER_INFO}') {
            $formattedMessage = $this->formatUserInfoMessage($message);
        } elseif ($formattedMessage === '{ALERT_REPORT}') {
            $formattedMessage = $this->formatAlertReportMessage();
        }

        // Форматируем дату в формат дд.мм.гггг чч:мм
        $formattedDate = '';
        if ($message->created_at) {
            $timestamp = strtotime($message->created_at);
            $formattedDate = date('d.m.Y H:i', $timestamp);
        }

        $data = [
            'id' => $message->id,
            'support_id' => $message->support_id,
            'user_id' => $message->user_id,
            'message' => $formattedMessage,
            'user' => $message->user ? [
                'id' => $message->user->id,
                'username' => $message->user->username,
                'avatar' => $message->user->getAvatar(),
                'avatar_frame_url' => $message->user->getAvatarFrameImageUrl(),
            ] : null,
            'files' => $files,
            'created_at' => $formattedDate,
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

    /**
     * Форматирование сообщения USER_INFO
     */
    protected function formatUserInfoMessage($message)
    {
        $ticket = $message->support;
        $ticketUser = $ticket ? $ticket->user : null;
        
        if (!$ticketUser) {
            return 'Информация о пользователе недоступна';
        }

        $server = $ticket && $ticket->server ? $ticket->server : null;
        $serverName = $server ? $server->name : 'неизвестно';
        
        $lines = [];
        $lines[] = "Сервер игрока: {$serverName}";
        
        if ($ticketUser->userProfile && $ticketUser->userProfile->trade_link) {
            $lines[] = "Трейд ссылка игрока: {$ticketUser->userProfile->trade_link}";
        }
        
        $lines[] = "Steam ID: {$ticketUser->steam_id}";
        
        // Получаем последние репорты
        $reports = \common\models\statistics\Reports::find()
            ->andWhere(['steam_id' => $ticketUser->steam_id])
            ->orderBy(['id' => SORT_DESC])
            ->limit(3)
            ->all();
        
        if (empty($reports)) {
            $lines[] = "Последние репорты игрока: Игрок не отправил ни одного репорта!";
        } else {
            $lines[] = "Последние репорты игрока:";
            foreach ($reports as $report) {
                if ($report->user) {
                    $lines[] = "- {$report->user->username} ({$report->user->steam_id}) - Причина: {$report->reason}";
                }
            }
        }
        
        return implode("\n", $lines);
    }

    /**
     * Найти сервер по IP и PORT
     * 
     * @param string|null $serverIp
     * @param string|null $serverPort
     * @return Servers|null
     */
    private function findServer($serverIp = null, $serverPort = null)
    {
        if (!$serverIp || !$serverPort) {
            return null;
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(60)
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        /** @var Servers $server */
        $server = null;

        // Поиск по IP и PORT
        foreach ($servers as $_server) {
            // Сравниваем IP (может быть в разных форматах: с портом или без)
            $serverIpClean = $this->cleanIpAddress($_server->ip);
            $requestIpClean = $this->cleanIpAddress($serverIp);

            // Также проверяем text_ip, если он есть
            $serverTextIpClean = !empty($_server->text_ip) ? $this->cleanIpAddress($_server->text_ip) : null;

            $ipMatches = ($serverIpClean == $requestIpClean) ||
                        ($serverTextIpClean && $serverTextIpClean == $requestIpClean);

            if ($ipMatches && $_server->port == (int)$serverPort) {
                $server = $_server;
                break;
            }
        }

        return $server;
    }

    /**
     * Очистить IP адрес от порта и привести к единому формату
     * 
     * @param string $ip
     * @return string
     */
    private function cleanIpAddress($ip)
    {
        if (empty($ip)) {
            return '';
        }

        // Убираем порт, если он есть (формат: ip:port)
        $parts = explode(':', $ip);
        $ipOnly = $parts[0];

        // Убираем пробелы
        $ipOnly = trim($ipOnly);

        return $ipOnly;
    }

    /**
     * Форматирование сообщения ALERT_REPORT
     */
    protected function formatAlertReportMessage()
    {
        return "Если вы хотите пожаловаться на игрока, нажмите в игре кнопку F7. Мы видим все ваши жалобы в игре, тикет в поддержку создавать не нужно. Если у вас есть доказательства и откаты вы можете приложить их по кнопке вложения файлов.";
    }
}

















<?php

namespace api\controllers\v1;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use common\models\user\User;
use common\models\user\UserProfile;
use common\models\user\UserBalance;
use common\models\user\UserTree;
use common\models\profit\Profit;
use common\models\skindrops\Skindrops;
use common\models\user\UserPayoutSkins;
use common\models\invoice\Deposit;
use common\models\invoice\Invoice;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\tasks_v2\TaskV2;
use common\models\box\DropFavorite;
use frontend\forms\profile\ProfileForm;
use frontend\forms\user\TransferForm;
use frontend\forms\promocode\UserPromocodeForm;
use frontend\forms\promocode\PromocodeForm;
use frontend\forms\market\PaymentForm;
use api\components\jwt\JwtAuthFilter;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с данными пользователя
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="User")
 */
class UserController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для всех методов, кроме поиска
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['options', 'search'],
        ];

        return $behaviors;
    }

    /**
     * Получение или обновление профиля пользователя
     * 
     * @OA\Get(
     *     path="/v1/user/profile",
     *     operationId="getUserProfile",
     *     tags={"User"},
     *     summary="Получить профиль пользователя",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Профиль пользователя")
     * )
     * @OA\Put(
     *     path="/v1/user/profile",
     *     operationId="updateUserProfile",
     *     tags={"User"},
     *     summary="Обновить профиль пользователя",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Профиль обновлен")
     * )
     */
    public function actionProfile()
    {
        $user = $this->getCurrentUser();

        if (empty($user->userProfile)) {
            UserProfile::createModel($user, $user->username);
            $user->userProfile->name = $user->username;
            $user->userProfile->save(false);
        }

        if (Yii::$app->request->isGet) {
            return $this->successResponse([
                'id' => $user->id,
                'username' => $user->username,
                'steam_id' => $user->steam_id,
                'avatar' => $user->getAvatar(),
                'profile' => [
                    'name' => $user->userProfile->name ?? $user->username,
                    'trade_link' => $user->userProfile->trade_link ?? null,
                    'raid_notify' => (bool)($user->raid_notify ?? false),
                    'ban_notify' => (bool)($user->ban_notify ?? false),
                    'is_hide_online' => (bool)($user->userProfile->is_hide_online ?? false),
                    'is_hide_team' => (bool)($user->userProfile->is_hide_team ?? false),
                    'youtube_link' => $user->userProfile->youtube_link ?? null,
                    'twitch_link' => $user->userProfile->twitch_link ?? null,
                    'kick_link' => $user->userProfile->kick_link ?? null,
                    'vk_link' => $user->userProfile->vk_link ?? null,
                    'telegram_link' => $user->userProfile->telegram_link ?? null,
                    'telegram_chat_id' => !empty($user->telegram_chat_id) ? (string)(int)$user->telegram_chat_id : null,
                    'telegramBotUsername' => (string)(Yii::$app->settings->get('telegram_personal_bot_username') ?? ''),
                    'discord_id' => $user->discord_id ? (string)$user->discord_id : null,
                    'twitch_id' => $user->twitch_id ? (string)$user->twitch_id : null,
                    'kick_id' => $user->kick_id ? (string)$user->kick_id : null,
                ],
            ]);
        }

        // PUT - обновление профиля
        if (Yii::$app->request->isPut || Yii::$app->request->isPost) {
            $model = ProfileForm::findOne($user->userProfile->id);
            // JSON body (PUT от фронта) приходит в getBodyParams(), form — в post()
            $post = Yii::$app->request->getBodyParams() ?: Yii::$app->request->post();
            // Fallback: для PUT часто тело не парсится в getBodyParams() — читаем raw JSON
            if (empty($post) && (Yii::$app->request->isPut || Yii::$app->request->isPost)) {
                $raw = Yii::$app->request->getRawBody();
                if (!empty($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $post = $decoded;
                    }
                }
            }

            if (!empty($post)) {
                $model->load($post, '') || $model->load($post);
                // Явно выставляем поля из тела (load может не подхватить всё из-за formName/attributes)
                if (array_key_exists('raid_notify', $post)) {
                    $model->raid_notify = (int)$post['raid_notify'];
                }
                if (array_key_exists('ban_notify', $post)) {
                    $model->ban_notify = (int)$post['ban_notify'];
                }
                if (array_key_exists('is_hide_online', $post)) {
                    $value = $post['is_hide_online'];
                    $model->is_hide_online = is_array($value) ? (bool)end($value) : (bool)$value;
                }
                if (array_key_exists('is_hide_team', $post)) {
                    $value = $post['is_hide_team'];
                    $model->is_hide_team = is_array($value) ? (bool)end($value) : (bool)$value;
                }
                if (array_key_exists('trade_link', $post)) {
                    $model->trade_link = $post['trade_link'] !== null && $post['trade_link'] !== '' ? trim((string)$post['trade_link']) : null;
                }
                if (array_key_exists('youtube_link', $post)) {
                    $model->youtube_link = $post['youtube_link'] !== null && $post['youtube_link'] !== '' ? trim((string)$post['youtube_link']) : null;
                }
                if (array_key_exists('twitch_link', $post)) {
                    $model->twitch_link = $post['twitch_link'] !== null && $post['twitch_link'] !== '' ? trim((string)$post['twitch_link']) : null;
                }
                if (array_key_exists('kick_link', $post)) {
                    $model->kick_link = $post['kick_link'] !== null && $post['kick_link'] !== '' ? trim((string)$post['kick_link']) : null;
                }
                if (array_key_exists('vk_link', $post)) {
                    $model->vk_link = $post['vk_link'] !== null && $post['vk_link'] !== '' ? trim((string)$post['vk_link']) : null;
                }
                if (array_key_exists('telegram_link', $post)) {
                    $model->telegram_link = $post['telegram_link'] !== null && $post['telegram_link'] !== '' ? trim((string)$post['telegram_link']) : null;
                }
            } else {
                return $this->errorResponse('INVALID_DATA', 'Неверные данные: тело запроса пусто или не JSON', [], 400);
            }

            if ($model->saveRecord()) {
                return $this->successResponse([
                    'message' => 'Настройки успешно сохранены',
                ]);
            }

            return $this->validationErrorResponse($model);
        }
    }

    /**
     * Установка текущего сервера пользователя (для выдачи предметов и т.д.)
     *
     * @OA\Put(
     *     path="/v1/user/current-server",
     *     operationId="setCurrentServer",
     *     tags={"User"},
     *     summary="Установить текущий сервер пользователя",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"serverId"},
     *                 @OA\Property(property="serverId", type="integer", description="ID сервера")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Сервер обновлён"),
     *     @OA\Response(response=400, description="Неверный serverId"),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionCurrentServer()
    {
        $user = $this->getCurrentUser();

        if (!Yii::$app->request->isPut && !Yii::$app->request->isPost) {
            $server = $user->server;
            return $this->successResponse([
                'server' => $server ? [
                    'id' => $server->id,
                    'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: $server->tag),
                    'tag' => $server->tag,
                ] : null,
            ]);
        }

        $post = Yii::$app->request->getBodyParams() ?: Yii::$app->request->post();
        if (empty($post)) {
            $raw = Yii::$app->request->getRawBody();
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $post = $decoded;
                }
            }
        }

        $serverId = isset($post['serverId']) ? (int) $post['serverId'] : null;
        if (empty($serverId)) {
            return $this->errorResponse('INVALID_DATA', 'Не указан serverId', [], 400);
        }

        $server = Servers::findOne($serverId);
        if (!$server || !in_array($server->status, [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE], true)) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден или недоступен', [], 404);
        }

        $user->server_id = $server->id;
        if (!$user->save(false)) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении', [], 500);
        }

        return $this->successResponse([
            'server' => [
                'id' => $server->id,
                'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: $server->tag),
                'tag' => $server->tag,
            ],
        ]);
    }

    /**
     * Сохранение социальных ссылок
     * 
     * @OA\Put(
     *     path="/v1/user/social-links",
     *     operationId="updateSocialLinks",
     *     tags={"User"},
     *     summary="Обновить социальные ссылки пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="youtube_link", type="string", example="https://youtube.com/channel/..."),
     *                 @OA\Property(property="twitch_link", type="string", example="https://twitch.tv/..."),
     *                 @OA\Property(property="vk_link", type="string", example="https://vk.com/..."),
     *                 @OA\Property(property="telegram_link", type="string", example="https://t.me/...")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Социальные ссылки обновлены",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSocialLinks()
    {
        $user = $this->getCurrentUser();

        if (empty($user->userProfile)) {
            UserProfile::createModel($user, $user->username);
        }

        $model = ProfileForm::findOne($user->userProfile->id);
        $post = Yii::$app->request->post();

        $model->youtube_link = $post['youtube_link'] ?? null;
        $model->twitch_link = $post['twitch_link'] ?? null;
        $model->vk_link = $post['vk_link'] ?? null;
        $model->telegram_link = $post['telegram_link'] ?? null;

        if ($model->saveRecord()) {
            return $this->successResponse([
                'message' => 'Социальные ссылки успешно сохранены',
            ]);
        } else {
            return $this->validationErrorResponse($model);
        }
    }

    /**
     * Получение всех балансов пользователя
     * 
     * @OA\Get(
     *     path="/v1/user/balance",
     *     operationId="getUserBalance",
     *     tags={"User"},
     *     summary="Получить все балансы пользователя",
     *     description="Требует JWT авторизации. Возвращает personal, skins и referral балансы.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Балансы пользователя",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionBalance()
    {
        $user = $this->getCurrentUser();

        $personalBalance = $user->getPersonalBalance();
        $skinsBalance = $user->getSkinsBalance();
        $referralBalance = $user->getReferralBalance();

        return $this->successResponse([
            'personal' => [
                'balance' => (float)$personalBalance->balance,
                'balanceCeil' => (int)ceil($personalBalance->balance),
                'balanceFormat' => $personalBalance->getBalanceFormat(),
            ],
            'skins' => [
                'balance' => (float)$skinsBalance->balance,
                'balanceCeil' => (int)ceil($skinsBalance->balance),
            ],
            'referral' => [
                'balance' => (float)$referralBalance,
            ],
        ]);
    }

    /**
     * Получение форматированного баланса (для виджетов)
     * 
     * @OA\Get(
     *     path="/v1/user/get-balance",
     *     operationId="getFormattedBalance",
     *     tags={"User"},
     *     summary="Получить форматированный баланс (для виджетов)",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Форматированный баланс",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionGetBalance()
    {
        $user = $this->getCurrentUser();
        $personalBalance = $user->getPersonalBalance();

        return $this->successResponse([
            'balanceStr' => $personalBalance->getBalanceFormat(),
            'balance' => $personalBalance->balanceCeil,
        ]);
    }

    /**
     * Получение баланса скинов
     * 
     * @OA\Get(
     *     path="/v1/user/skins-balance",
     *     operationId="getSkinsBalance",
     *     tags={"User"},
     *     summary="Получить баланс скинов",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Баланс скинов",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSkinsBalance()
    {
        $user = $this->getCurrentUser();
        $skinsBalance = $user->getSkinsBalance();

        return $this->successResponse([
            'balance' => (float)$skinsBalance->balance,
            'currency' => 'skins_coins',
        ]);
    }

    /**
     * Получение статистики по скинам
     * 
     * @OA\Get(
     *     path="/v1/user/skins-statistics",
     *     operationId="getSkinsStatistics",
     *     tags={"User"},
     *     summary="Получить статистику по скинам",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Статистика по скинам",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSkinsStatistics()
    {
        $user = $this->getCurrentUser();
        $steamId = $user->steam_id;

        $skinCount = Skindrops::find()
            ->where(['steam_id' => $steamId])
            ->count();

        $skinsBalance = $user->getSkinsBalance();
        $totalWonAmount = Profit::find()
            ->where(['user_balance_id' => $skinsBalance->id])
            ->andWhere(['type' => Profit::TYPE_WINNER_SKINS])
            ->sum('amount') ?: 0;

        $averageWinAmount = $skinCount > 0 ? round($totalWonAmount / $skinCount, 2) : 0;

        return $this->successResponse([
            'skinCount' => (int)$skinCount,
            'totalWonAmount' => (float)$totalWonAmount,
            'averageWinAmount' => (float)$averageWinAmount,
        ]);
    }

    /**
     * Получение истории операций со скинами
     * 
     * @OA\Get(
     *     path="/v1/user/skins-operations",
     *     operationId="getSkinsOperations",
     *     tags={"User"},
     *     summary="Получить историю операций со скинами",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Номер страницы",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="Размер страницы",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="История операций",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSkinsOperations()
    {
        $user = $this->getCurrentUser();
        $page = (int)Yii::$app->request->get('page', 1);
        $pageSize = (int)Yii::$app->request->get('pageSize', 10);

        $steamId = $user->steam_id;

        // Полученные скины (Skindrops)
        $skins = Skindrops::find()
            ->select([
                'name' => 'name',
                'image' => 'image',
                'amount' => 'real_price',
                'created_at' => 'created_at'
            ])
            ->where(['steam_id' => $steamId])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($skins as &$skin) {
            $skin['type'] = 'profit';
            $skin['status'] = 'Зачислено';
            $skin['statusKey'] = 'SUCCESS';
        }

        // Выведенные скины (UserPayoutSkins)
        $payouts = UserPayoutSkins::find()
            ->select([
                'statusKey' => 'status',
                'amount',
                'image',
                'image300',
                'name',
                'created_at'
            ])
            ->where(['user_id' => $user->id])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($payouts as &$payout) {
            $payout['type'] = 'payout';
            $payout['amount'] = $payout['amount'] * (-1);
            $payout['status'] = ArrayHelper::getValue(UserPayoutSkins::getStatusList(), $payout['statusKey'], '');
            if ($payout['statusKey'] == UserPayoutSkins::STATUS_REJECT) {
                $payout['amount'] = 0;
            }
        }

        // Переводы в магазин
        $personalBalance = $user->getPersonalBalance();
        $transfers = Profit::find()
            ->select([
                'amount',
                'created_at'
            ])
            ->where(['IN', 'type', [Profit::TYPE_TRANSFER_SKINS]])
            ->andWhere(['user_balance_id' => $personalBalance->id])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($transfers as &$transfer) {
            $transfer['type'] = 'transfer';
            $transfer['amount'] = $transfer['amount'] * (-1);
            $transfer['status'] = 'Перевод в магазин';
            $transfer['image'] = null;
            $transfer['name'] = null;
        }

        // Объединяем все операции
        $operations = ArrayHelper::merge($payouts, $skins);
        $operations = ArrayHelper::merge($operations, $transfers);

        // Сортируем по дате
        usort($operations, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Пагинация
        $totalCount = count($operations);
        $offset = ($page - 1) * $pageSize;
        $operations = array_slice($operations, $offset, $pageSize);

        return $this->successResponse([
            'operations' => $operations,
        ], [
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalCount' => $totalCount,
                'totalPages' => (int)ceil($totalCount / $pageSize),
            ],
        ]);
    }

    /**
     * Получение истории операций (прибыли, траты, пополнения)
     * 
     * @OA\Get(
     *     path="/v1/user/history",
     *     operationId="getUserHistory",
     *     tags={"User"},
     *     summary="Получить историю операций пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="depositId",
     *         in="query",
     *         required=false,
     *         description="ID депозита для проверки статуса",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Номер страницы",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="Размер страницы",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="История операций",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionHistory($depositId = null)
    {
        $user = $this->getCurrentUser();
        $page = (int)Yii::$app->request->get('page', 1);
        $pageSize = (int)Yii::$app->request->get('pageSize', 20);

        // Проверка статуса депозита если указан
        if (!empty($depositId)) {
            $deposit = Deposit::findOne($depositId);
            if (!empty($deposit) && $deposit->user_id === $user->id && $deposit->status === Deposit::STATUS_WAIT_CONFIRM) {
                $status = $deposit->check();
                // Статус обновлен, можно вернуть информацию
            }
        }

        $personalBalance = $user->getPersonalBalance();
        $operations = [];

        // Прибыли (Profits)
        $profits = Profit::find()
            ->select(['type', 'amount', 'comment', 'created_at'])
            ->where(['user_balance_id' => $personalBalance->id])
            ->andWhere(['IN', 'type', [
                Profit::TYPE_REFERRAL,      // Партнерская программа
                Profit::TYPE_BONUS,         // Бонус
                Profit::TYPE_WINNER_SKINS,  // Выигран скин
                Profit::TYPE_PROMOCODE,     // Промокод
                Profit::TYPE_SELL_DROP,     // Продажа предметов
                Profit::TYPE_DAILY_REWARD_LIST, // Ежедневная награда
                Profit::TYPE_ACHIEVEMENT,   // Достижения
                Profit::TYPE_TASK,          // Задания
                Profit::TYPE_TASK_V2,       // Задания v2
                Profit::TYPE_TRANSFER_SKINS,   // Перевод из скинов в магазин
                Profit::TYPE_TRANSFER_REFERRAL, // Перевод из реферальной системы
            ]])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($profits as &$profit) {
            $profit['operation_type'] = 'profit';
            $profit['sum'] = '+' . $profit['amount'];
        }

        // Траты (Invoices)
        $invoices = Invoice::find()
            ->select(['amount', 'comment', 'created_at'])
            ->where(['user_id' => $user->id])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($invoices as &$invoice) {
            $invoice['operation_type'] = 'invoice';
            $invoice['type'] = 'invoice';
            $invoice['sum'] = '-' . $invoice['amount'];
        }

        // Пополнения (Deposits)
        $deposits = Deposit::find()
            ->select(['amount', 'status', 'created_at'])
            ->where(['user_id' => $user->id])
            ->asArray()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        foreach ($deposits as &$deposit) {
            $deposit['operation_type'] = 'deposit';
            $deposit['type'] = 'deposit';
            $deposit['sum'] = $deposit['status'] == Deposit::STATUS_SUCCESS ? '+' . $deposit['amount'] : '0';
            $deposit['comment'] = 'Пополнение баланса';
        }

        // Объединяем все операции
        $operations = ArrayHelper::merge($profits, $invoices);
        $operations = ArrayHelper::merge($operations, $deposits);

        // Сортируем по дате
        usort($operations, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Пагинация
        $totalCount = count($operations);
        $offset = ($page - 1) * $pageSize;
        $operations = array_slice($operations, $offset, $pageSize);

        return $this->successResponse([
            'operations' => $operations,
        ], [
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalCount' => $totalCount,
                'totalPages' => (int)ceil($totalCount / $pageSize),
            ],
        ]);
    }

    /**
     * Перевод средств между балансами
     * 
     * @OA\Post(
     *     path="/v1/user/transfer/{type}",
     *     operationId="transferBalance",
     *     tags={"User"},
     *     summary="Перевести средства между балансами",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Тип перевода (referral, skins)",
     *         @OA\Schema(type="string", enum={"referral", "skins"})
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"amount"},
     *                 @OA\Property(property="amount", type="number", example=100, description="Сумма для перевода")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Перевод выполнен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры или недостаточно средств"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionTransfer($type)
    {
        $user = $this->getCurrentUser();

        $form = new TransferForm();
        $form->type = $type;

        // Устанавливаем максимальную сумму для перевода
        if ($type === TransferForm::TYPE_REFERRAL) {
            $balance = $user->getReferralBalance();
            $form->amount = $balance;
        } elseif ($type === TransferForm::TYPE_SKINS) {
            $balance = $user->getSkinsBalance();
            $form->amount = $balance->balance;
        } else {
            return $this->errorResponse('INVALID_TYPE', 'Неверный тип перевода', [], 400);
        }

        // JSON body от фронта приходит в getBodyParams()
        $params = Yii::$app->request->getBodyParams() ?: Yii::$app->request->post();
        if ($form->load($params, '')) {
            $result = $form->saveRecord();
            if ($result !== false) {
                return $this->successResponse([
                    'message' => 'Баланс пополнен на ' . $result . ' RUB',
                    'newBalance' => $user->getPersonalBalance()->balance,
                ]);
            } else {
                return $this->validationErrorResponse($form);
            }
        }

        return $this->errorResponse('INVALID_DATA', 'Неверные данные', [], 400);
    }

    /**
     * Получение информации о реферальной программе
     * 
     * @OA\Get(
     *     path="/v1/user/partner",
     *     operationId="getPartnerInfo",
     *     tags={"User"},
     *     summary="Получить информацию о реферальной программе",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Информация о реферальной программе",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    /**
     * Получение условий партнерской программы
     * 
     * @OA\Get(
     *     path="/v1/user/partner/conditions",
     *     operationId="getPartnerConditions",
     *     tags={"User"},
     *     summary="Получить условия партнерской программы",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Условия партнерской программы",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPartnerConditions()
    {
        try {
            // Получаем настройки из системы (можно расширить в будущем)
            $conditions = [
                'description' => 'Условия партнерской программы',
                // Можно добавить больше информации из настроек
            ];

            return $this->successResponse($conditions);
        } catch (\Throwable $e) {
            Yii::error('Error in actionPartnerConditions: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            return $this->errorResponse('SERVER_ERROR', 'Ошибка при получении условий партнерской программы: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Получение информации о том, как приглашать (ссылка, промокод, статистика)
     * 
     * @OA\Get(
     *     path="/v1/user/partner/invite",
     *     operationId="getPartnerInvite",
     *     tags={"User"},
     *     summary="Получить информацию о приглашении (ссылка, промокод, статистика)",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Информация о приглашении",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPartnerInvite()
    {
        try {
            $user = $this->getCurrentUser();
            // Загружаем userProfile для текущего пользователя
            if (!$user->userProfile) {
                $user = User::find()->where(['id' => $user->id])->with('userProfile')->one();
            }

            // Получаем детей (рефералов) для статистики
            $children = UserTree::find()
                ->where(['parent_user_id' => $user->id])
                ->andWhere(['NOT IN', 'user_id', [$user->id]])
                ->count();

            // Количество поигравших более часа
            $playedCount = UserTree::find()
                ->alias('ut')
                ->joinWith(['user.userProfile up'])
                ->andWhere(['ut.parent_user_id' => $user->id])
                ->andWhere(['NOT IN', 'ut.user_id', [$user->id]])
                ->andWhere(['up.parent_bonus' => 1])
                ->count();

            // Получаем баланс реферальной программы
            $referralBalance = 0;
            try {
                if ($user->userProfile && isset($user->userProfile->referral_bonus)) {
                    $referralBalance = $user->getReferralBalance();
                    if (!is_numeric($referralBalance) || $referralBalance < 0) {
                        $referralBalance = 0;
                    }
                }
            } catch (\Throwable $e) {
                Yii::error('Error getting referral balance: ' . $e->getMessage(), 'api');
                $referralBalance = 0;
            }

            // Получаем процент реферальной программы
            $referralPercent = 0;
            try {
                $referralPercent = $user->getReferralBonus();
            } catch (\Throwable $e) {
                Yii::error('Error getting referral bonus: ' . $e->getMessage(), 'api');
            }

            // Количество переходов по ссылке
            $referralClicks = $user->userProfile->referral_click ?? 0;

            // Формируем партнерскую ссылку
            $refCode = $user->ref_code ?? '';
            $baseUrl = Yii::$app->params['baseUrl'] ?? (Yii::$app->params['homePage'] ?? 'http://localhost');
            // Убираем api. из URL если есть
            $baseUrl = str_replace('api.', '', $baseUrl);
            $partnerLink = !empty($refCode) ? ($baseUrl . '/p/' . $refCode) : '';

            return $this->successResponse([
                'ref_code' => $refCode,
                'referral_link' => $partnerLink,
                'partnerLink' => $partnerLink, // Дублируем для совместимости
                'referral_percent' => (float)$referralPercent,
                'referral_clicks' => (int)$referralClicks,
                'registered_count' => (int)$children,
                'played_count' => (int)$playedCount,
                'referral_balance' => (float)$referralBalance,
            ]);
        } catch (\Throwable $e) {
            Yii::error('Error in actionPartnerInvite: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            return $this->errorResponse('SERVER_ERROR', 'Ошибка при получении информации о приглашении: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Получение списка рефералов пользователя
     * 
     * @OA\Get(
     *     path="/v1/user/partner/referrals",
     *     operationId="getPartnerReferrals",
     *     tags={"User"},
     *     summary="Получить список рефералов",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Список рефералов",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPartnerReferrals()
    {
        try {
            $user = $this->getCurrentUser();

            $children = UserTree::find()
                ->where(['parent_user_id' => $user->id])
                ->andWhere(['NOT IN', 'user_id', [$user->id]])
                ->with(['user', 'user.userProfile'])
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(100)
                ->all();

            $referrals = [];
            foreach ($children as $child) {
                if ($child->user) {
                    try {
                        $avatar = $child->user->getAvatar();
                    } catch (\Throwable $e) {
                        $avatar = '';
                    }

                    // Проверка, получил ли пользователь награду
                    $hasBonus = false;
                    $hasSkinSent = false;
                    if ($child->user->userProfile) {
                        $hasBonus = (bool)($child->user->userProfile->parent_bonus ?? false);
                        $hasSkinSent = (bool)($child->user->parent_skin_send ?? false);
                    }

                    // Проверка, играл ли пользователь более часа
                    $hasHourInServer = false;
                    try {
                        $hasHourInServer = $child->user->hasHourInServer();
                    } catch (\Throwable $e) {
                        // Игнорируем ошибку
                    }

                    $referrals[] = [
                        'id' => $child->user->id,
                        'userId' => $child->user->id,
                        'username' => $child->user->username ?? '',
                        'steam_id' => $child->user->steam_id ?? '',
                        'avatar' => $avatar,
                        'created_at' => $child->user->created_at ?? '',
                        'hasBonus' => $hasBonus,
                        'hasSkinSent' => $hasSkinSent,
                        'hasHourInServer' => $hasHourInServer,
                        'canGetReward' => $hasHourInServer && !($hasBonus && $hasSkinSent),
                    ];
                }
            }

            return $this->successResponse([
                'referrals' => $referrals,
                'total' => count($referrals),
            ]);
        } catch (\Throwable $e) {
            Yii::error('Error in actionPartnerReferrals: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            return $this->errorResponse('SERVER_ERROR', 'Ошибка при получении списка рефералов: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Получение или создание промокода пользователя для реферальной программы
     * 
     * @OA\Get(
     *     path="/v1/user/partner/promocode",
     *     operationId="getPartnerPromocode",
     *     tags={"User"},
     *     summary="Получить промокод пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Промокод пользователя",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     * @OA\Post(
     *     path="/v1/user/partner/promocode",
     *     operationId="createPartnerPromocode",
     *     tags={"User"},
     *     summary="Создать или обновить промокод пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"promocode"},
     *                 @OA\Property(property="promocode", type="string", example="MYCODE123", description="Промокод (минимум 5 символов, максимум 120)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Промокод создан или обновлен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные данные"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPartnerPromocode()
    {
        $user = $this->getCurrentUser();

        if (Yii::$app->request->isGet) {
            // GET - получение промокода
            return $this->successResponse([
                'promocode' => $user->promocode ?? null,
            ]);
        }

        // POST - создание/обновление промокода
        $post = Yii::$app->request->post();
        $promocode = trim($post['promocode'] ?? '');

        if (empty($promocode)) {
            return $this->errorResponse('INVALID_DATA', 'Промокод не может быть пустым', [], 400);
        }

        if (strlen($promocode) < 5 || strlen($promocode) > 120) {
            return $this->errorResponse('INVALID_DATA', 'Промокод должен быть от 5 до 120 символов', [], 400);
        }

        // Проверяем, не используется ли промокод другим пользователем
        $existingUser = User::find()
            ->where(['promocode' => $promocode])
            ->andWhere(['!=', 'id', $user->id])
            ->one();

        if ($existingUser) {
            return $this->errorResponse('PROMOCODE_EXISTS', 'Этот промокод уже используется другим пользователем', [], 400);
        }

        $user->promocode = $promocode;
        if ($user->save(false)) {
            return $this->successResponse([
                'promocode' => $user->promocode,
                'message' => 'Промокод успешно создан',
            ]);
        } else {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении промокода', [], 500);
        }
    }

    /**
     * Получение бонуса за приглашенного пользователя
     * 
     * @OA\Post(
     *     path="/v1/user/partner-bonus/{id}",
     *     operationId="getPartnerBonus",
     *     tags={"User"},
     *     summary="Получить бонус за приглашенного пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID приглашенного пользователя",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Бонус получен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Бонус уже получен или пользователь не является рефералом"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Пользователь не найден")
     * )
     */
    public function actionPartnerBonus($id)
    {
        $user = $this->getCurrentUser();

        $childUser = User::find()->where(['id' => (int)$id])->with(['userProfile', 'userTree'])->one();
        if (!$childUser) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $parentUser = $childUser->getParentUser();
        if (!$parentUser || $parentUser->id !== $user->id) {
            return $this->errorResponse('NOT_REFERRAL', 'Вы не приглашали данного игрока', [], 400);
        }

        if ($childUser->parent_skin_send && $childUser->userProfile && $childUser->userProfile->parent_bonus) {
            return $this->errorResponse('BONUS_ALREADY_RECEIVED', 'Награда уже получена', [], 400);
        }

        if (!$childUser->hasHourInServer()) {
            return $this->errorResponse('NO_HOUR', 'Игрок ещё не отыграл час на сервере', [], 400);
        }

        $referralBonusAmount = (int)Yii::$app->settings->get('referral_bonus', 0);
        if ($referralBonusAmount < 0) {
            $referralBonusAmount = 0;
        }

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // Бонус на баланс (реферальный процент)
            if ($childUser->userProfile && !$childUser->userProfile->parent_bonus) {
                $childUser->userProfile->parent_bonus = 1;
                $childUser->userProfile->save(false);

                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_REFERRAL;
                $profit->amount = $referralBonusAmount;
                $profit->user_balance_id = $user->getPersonalBalance()->id;
                $profit->comment = 'Бонус за приглашённого пользователя "' . ($childUser->username ?? '') . '"';
                $profit->created_at = date('Y-m-d H:i:s');
                $profit->save(false);
                $user->getPersonalBalance()->recalculateBalance();
            }

            // Скин на баланс скинов (как в старом frontend)
            if (!$childUser->parent_skin_send) {
                $childUser->parent_skin_send = 1;
                $childUser->save(false);

                $minSum = (float)Yii::$app->settings->get('referral_minSum', 0);
                $maxSum = (float)Yii::$app->settings->get('referral_maxSum', 0);
                if ($maxSum <= 0) {
                    $maxSum = 1000;
                }

                if (isset(Yii::$app->rustTm) && $minSum >= 0) {
                    $items = Yii::$app->rustTm->items();
                    if (is_array($items)) {
                        shuffle($items);
                        foreach ($items as $item) {
                            $price = isset($item['price']) ? (float)$item['price'] : 0;
                            if ($price < $minSum || $price > $maxSum) {
                                continue;
                            }
                            $name = $item['name'] ?? '';
                            $image = $item['image'] ?? '';

                            $skindrop = new Skindrops();
                            $skindrop->name = $name;
                            $skindrop->steam_id = $user->steam_id;
                            $skindrop->player = $user->username;
                            $skindrop->price = ceil($price);
                            $skindrop->real_price = ceil($price);
                            $skindrop->image = $image;
                            $skindrop->created_at = date('Y-m-d H:i:s');
                            $skindrop->save(false);

                            $skinProfit = new Profit();
                            $skinProfit->user_balance_id = $user->getSkinsBalance()->id;
                            $skinProfit->amount = ceil($price);
                            $skinProfit->comment = 'Выигрыш скина';
                            $skinProfit->status = 1;
                            $skinProfit->type = Profit::TYPE_WINNER_SKINS;
                            $skinProfit->created_at = date('Y-m-d H:i:s');
                            $skinProfit->save(false);
                            $user->getSkinsBalance()->recalculateBalance();
                            break;
                        }
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('actionPartnerBonus: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            return $this->errorResponse('SERVER_ERROR', 'Ошибка при получении награды: ' . $e->getMessage(), [], 500);
        }

        return $this->successResponse([
            'message' => 'Награда успешно получена',
        ]);
    }

    /**
     * Активация промокода
     * 
     * @OA\Post(
     *     path="/v1/user/promocode",
     *     operationId="activatePromocode",
     *     tags={"User"},
     *     summary="Активировать промокод",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"promocode"},
     *                 @OA\Property(property="promocode", type="string", example="PROMO123", description="Промокод для активации")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Промокод активирован",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверный промокод или промокод уже использован"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPromocode()
    {
        $user = $this->getCurrentUser();

        $promocodeForm = UserPromocodeForm::findOne($user->id);
        if (!$promocodeForm) {
            return $this->errorResponse('FORM_NOT_FOUND', 'Форма промокода не найдена', [], 404);
        }

        if ($promocodeForm->load(Yii::$app->request->post(), '')) {
            if ($promocodeForm->saveRecord()) {
                return $this->successResponse([
                    'message' => 'Баланс пополнен на 50 RUB',
                    'newBalance' => $user->getPersonalBalance()->balance,
                ]);
            } else {
                return $this->validationErrorResponse($promocodeForm);
            }
        }

        return $this->errorResponse('INVALID_DATA', 'Неверные данные', [], 400);
    }

    /**
     * Активация промокода (как в шапке сайта site/promocode — ввод кода для пополнения баланса).
     *
     * @OA\Post(
     *     path="/v1/user/promocode/activate",
     *     operationId="activatePromocode",
     *     tags={"User"},
     *     summary="Активировать промокод (пополнение баланса)",
     *     description="Требует JWT. Ввод промокода из шапки сайта — начисление на баланс.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"code"},
     *                 @OA\Property(property="code", type="string", example="PROMO123", description="Код промокода для активации")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Промокод активирован"),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionActivatePromocode()
    {
        $user = $this->getCurrentUser();

        $form = new PromocodeForm();
        if (!$form->load(Yii::$app->request->post(), '')) {
            return $this->errorResponse('INVALID_DATA', 'Неверные данные', [], 400);
        }
        $model = $form->saveRecord();
        if ($model !== null && $model !== false) {
            $amount = is_object($model) && isset($model->amount) ? (int) $model->amount : 50;
            return $this->successResponse([
                'message' => Yii::t('common', 'Баланс пополнен на {PARAMS_PROMSUM} RUB', ['PARAMS_PROMSUM' => $amount]),
                'newBalance' => (int) $user->getPersonalBalance()->balance,
            ]);
        }
        return $this->validationErrorResponse($form);
    }

    /**
     * Создание платежа
     * POST /api/v1/user/payment
     */
    /**
     * Создание платежа (алиас для PaymentController)
     * 
     * @OA\Post(
     *     path="/v1/user/payment",
     *     operationId="createUserPayment",
     *     tags={"User"},
     *     summary="Создать платеж (алиас)",
     *     description="Требует JWT авторизации. Алиас для создания платежа.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"amount", "payment_id"},
     *                 @OA\Property(property="amount", type="integer", example=1000),
     *                 @OA\Property(property="payment_id", type="integer", example=18)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Платеж создан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPayment()
    {
        $user = $this->getCurrentUser();

        $modelForm = new PaymentForm();
        if ($modelForm->load(Yii::$app->request->post(), '')) {
            try {
                $response = $modelForm->createOperation();
                if (!empty($response['paymentURL'])) {
                    return $this->successResponse([
                        'payment_url' => $response['paymentURL'],
                        'payment_id' => $response['paymentId'] ?? null,
                    ]);
                }
                if (!empty($response['template'])) {
                    return $this->successResponse([
                        'template' => $response['template'],
                        'data' => $response,
                    ]);
                }
                return $this->errorResponse('PAYMENT_ERROR', 'Ошибка при создании платежа', [], 400);
            } catch (\Exception $e) {
                Yii::error('Payment creation error: ' . $e->getMessage(), 'payment');
                return $this->errorResponse('PAYMENT_ERROR', $e->getMessage(), [], 400);
            }
        }

        return $this->validationErrorResponse($modelForm);
    }

    /**
     * Получение данных для homepage (статистика, награды)
     * 
     * @OA\Get(
     *     path="/v1/user/homepage-data",
     *     operationId="getHomepageData",
     *     tags={"User"},
     *     summary="Получить данные пользователя для главной страницы",
     *     description="Возвращает статистику пользователя, награды и другую информацию для отображения на главной странице",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Данные для homepage",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionHomepageData()
    {
        $user = $this->getCurrentUser();
        
        // Получаем активный сервер (кэшируем для пользователя на 1 минуту)
        $serverCacheKey = 'homepage_server_user_' . $user->id;
        $server = Yii::$app->cache->get($serverCacheKey);
        
        if ($server === false) {
        $server = null;
        
        // Пытаемся получить текущий сервер пользователя
        if (!empty($user->server_tag)) {
            $server = Servers::find()
                ->where(['tag' => $user->server_tag, 'status' => 1])
                ->one();
        }
        
        // Если у пользователя нет сервера или сервер не найден, используем getCurrentServer()
        if (!$server) {
            $userServer = $user->getCurrentServer();
            if ($userServer && $userServer->status == 1) {
                $server = $userServer;
            }
        }
        
        // Если все еще нет сервера, берем дефолтный
        if (!$server) {
                $defaultCacheKey = 'homepage_server_' . (Yii::$app->params['statisticsServerDefault'] ?? 'max3');
                $server = Yii::$app->cache->get($defaultCacheKey);
            
            if ($server === false) {
                $defaultServerTag = Yii::$app->params['statisticsServerDefault'] ?? 'max3';
                $server = Servers::find()
                    ->where(['tag' => $defaultServerTag, 'status' => 1])
                    ->one();
                
                if (!$server) {
                    // Если сервер не найден, берем первый активный
                    $server = Servers::find()
                        ->where(['status' => 1])
                        ->orderBy(['sort' => SORT_ASC])
                        ->one();
                }
                
                    // Кэшируем дефолтный сервер на 5 минут
                if ($server) {
                        Yii::$app->cache->set($defaultCacheKey, $server, 5 * 60);
                }
                }
            }
            
            // Кэшируем сервер для пользователя на 1 минуту
            if ($server) {
                Yii::$app->cache->set($serverCacheKey, $server, 60);
            }
        }
        
        // Получаем статистику пользователя (кэшируем на 2 минуты)
        $userStats = [];
        if ($server) {
            $userStatsCacheKey = 'homepage_user_stats_' . $user->steam_id . '_' . $server->id . '_' . $server->wipe;
            $userStats = Yii::$app->cache->get($userStatsCacheKey);
            
            if ($userStats === false) {
            // Используем getStats как в старой версии для получения статистики пользователя
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            $stats = Statistics::getStats($server, $user->steam_id, false, $wipeDate);
            
            if (!empty($stats) && !empty($stats['player'])) {
                $player = $stats['player'];
                $kills = isset($player['kills']) ? (int)$player['kills'] : 0;
                $deaths = isset($player['deaths']) ? (int)$player['deaths'] : 0;
                $kd = $deaths > 0 ? round($kills / $deaths, 2) : ($kills > 0 ? $kills : 0);
                
                $userStats = [
                    'kills' => $kills,
                    'deaths' => $deaths,
                    'kd' => $kd,
                    'scientists' => isset($player['scientists']) ? (int)$player['scientists'] : 0,
                    'sulfur.ore' => isset($player['sulfur.ore']) ? (int)$player['sulfur.ore'] : 0,
                    'metal.ore' => isset($player['metal.ore']) ? (int)$player['metal.ore'] : 0,
                    'stones' => isset($player['stones']) ? (int)$player['stones'] : 0,
                    'wood' => isset($player['wood']) ? (int)$player['wood'] : 0,
                ];
                } else {
                    $userStats = [];
                }
                
                // Кэшируем на 2 минуты
                Yii::$app->cache->set($userStatsCacheKey, $userStats, 2 * 60);
            }
        }
        
        // Получаем награды из заданий (кэшируем активные задания)
        $awards = [];
        $tasksCacheKey = 'homepage_tasks_active';
        $allTasks = Yii::$app->cache->get($tasksCacheKey);
        
        if ($allTasks === false) {
            // Получаем все активные задания
            $allTasks = TaskV2::find()
                ->select(['id', 'title', 'image_path', 'sort'])
                ->where(['is_active' => 1])
                ->orderBy(['sort' => SORT_ASC])
                ->asArray()
                ->all();
            
            // Кэшируем на 10 минут
            Yii::$app->cache->set($tasksCacheKey, $allTasks, 10 * 60);
        }
        
        // Получаем выполненные задания пользователя одним запросом (кэшируем на 1 минуту)
        $completionsCacheKey = 'homepage_completions_' . $user->id;
        $userCompletions = Yii::$app->cache->get($completionsCacheKey);
        
        if ($userCompletions === false) {
            $userCompletions = \common\models\tasks_v2\TaskV2UserCompletion::find()
                ->select(['task_id', 'count_completed'])
                ->where(['user_id' => $user->id])
                ->andWhere(['>', 'count_completed', 0])
                ->indexBy('task_id')
                ->asArray()
                ->all();
            
            // Кэшируем на 1 минуту
            Yii::$app->cache->set($completionsCacheKey, $userCompletions, 60);
        }
        
        $completedTasks = 0;
        $totalTasks = count($allTasks);
        $completedAwardsList = [];
        
        // Формируем массив выполненных заданий
        foreach ($allTasks as $task) {
            $taskId = is_array($task) ? $task['id'] : $task->id;
            $completion = $userCompletions[$taskId] ?? null;
            $completed = $completion && (is_array($completion) ? $completion['count_completed'] : $completion->count_completed) > 0;
            
            if ($completed) {
                $completedTasks++;
                
                // Получаем изображение задания
                $imagePath = is_array($task) ? $task['image_path'] : $task->image_path;
                if (empty($imagePath)) {
                    $image = '/images/design/icons/128px/task-default.png';
                } else {
                    $image = Yii::$app->settings->get('s3_publicUrl') . '/' . ltrim($imagePath, '/');
                }
                
                $awardData = [
                    'id' => $taskId,
                    'name' => Yii::t('database', is_array($task) ? $task['title'] : $task->title),
                    'image' => $image,
                    'completed' => true,
                ];
                
                $completedAwardsList[] = $awardData;
            }
        }
        
        // Берем только выполненные задания (не более 7, как в старой версии)
        $awards = array_slice($completedAwardsList, 0, 7);
        
        // Формируем ссылку на статистику
        $userStatsLink = null;
        if ($server) {
            $baseUrl = Yii::$app->params['baseUrl'] ?? (Yii::$app->params['homePage'] ?? 'http://localhost');
            $userStatsLink = $baseUrl . '/servers/' . $server->tag . '?steam_id=' . $user->steam_id;
        }
        
        // Получаем статистику проекта (кэшируется внутри метода на 7 дней)
        $projectStatsCacheKey = 'homepage_project_stats';
        $projectStats = Yii::$app->cache->get($projectStatsCacheKey);
        
        if ($projectStats === false) {
        $projectStats = \common\models\statistics\Statistics::projectStats();
            // Кэшируем на 5 минут (внутри projectStats кэш на 7 дней, но для homepage делаем короче)
            Yii::$app->cache->set($projectStatsCacheKey, $projectStats, 5 * 60);
        }
        
        // Список ID товаров в избранном (drop_id)
        $favoriteDropIds = DropFavorite::getFavoriteDropIds($user->id);
        
        return $this->successResponse([
            'username' => $user->username,
            'userStats' => $userStats,
            'awards' => $awards,
            'awardsStats' => [
                'completed' => $completedTasks,
                'total' => $totalTasks,
            ],
            'userStatsLink' => $userStatsLink,
            'serverActiveTag' => $server ? $server->tag : null,
            'projectStats' => [
                'users' => $projectStats['users'] ?? 0,
                'online' => $projectStats['online'] ?? 0,
                'count' => $projectStats['count'] ?? 0,
            ],
            'favoriteDropIds' => $favoriteDropIds,
        ]);
    }

    /**
     * Поиск пользователей по нику или Steam ID
     * 
     * @OA\Get(
     *     path="/v1/user/search",
     *     operationId="searchUsers",
     *     tags={"User"},
     *     summary="Поиск пользователей по нику или Steam ID",
     *     description="Публичный метод, авторизация не требуется. Возвращает список пользователей с полной информацией.",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         description="Поисковый запрос (ник или Steam ID)",
     *         @OA\Schema(type="string", example="player123")
     *     ),
     *     @OA\Parameter(
     *         name="serverId",
     *         in="query",
     *         required=false,
     *         description="ID сервера для формирования ссылки на статистику",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="query",
     *         required=false,
     *         description="Тег сервера для формирования ссылки на статистику",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Результаты поиска",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Пустой запрос")
     * )
     */
    public function actionSearch()
    {
        $q = Yii::$app->request->get('q');
        $serverId = Yii::$app->request->get('serverId');
        $serverTag = Yii::$app->request->get('serverTag');

        if (empty($q)) {
            return $this->errorResponse('INVALID_QUERY', 'Запрос не может быть пустым', [], 400);
        }

        // Ограничиваем поиск пользователями, которые заходили за последние 3 месяца
        // Это значительно ускоряет поиск, так как не нужно сканировать всех пользователей
        $threeMonthsAgo = date('Y-m-d H:i:s', strtotime('-3 months'));
        
        // Проверяем, является ли запрос числом (steam_id) или строкой (username)
        $isNumeric = is_numeric($q);
        
        // Поиск пользователей по нику или steam_id, сортировка по дате последнего визита
        // Ограничиваем только активными пользователями (за последние 3 месяца)
        $query = User::find()
            ->select(['id', 'username', 'steam_id', 'last_visit_server_at'])
            ->andWhere([
                'or',
                ['>=', 'last_visit_server_at', $threeMonthsAgo],
                ['IS', 'last_visit_server_at', null] // Включаем пользователей без даты (новые)
            ]);
        
        if ($isNumeric) {
            // Если запрос числовой, ищем по steam_id (точное совпадение быстрее)
            $query->andWhere(['steam_id' => $q]);
        } else {
            // Если запрос строковый, ищем по username (LIKE)
            $query->andWhere(['LIKE', 'username', $q]);
        }
        
        $users = $query
            ->orderBy(['last_visit_server_at' => SORT_DESC])
            ->limit(10)
            ->all();

        $items = [];
        $baseUrl = Yii::$app->params['baseUrl'] ?? (Yii::$app->params['homePage'] ?? 'http://localhost');
        $baseUrl = str_replace('api.', '', $baseUrl);

        foreach ($users as $user) {
            try {
                $avatar = $user->getAvatar();
            } catch (\Throwable $e) {
                $avatar = '';
            }

            // Формируем ссылку на статистику
            $statsLink = null;
            if ($serverTag) {
                $statsLink = $baseUrl . '/servers/' . $serverTag . '/' . $user->steam_id;
            } elseif ($serverId) {
                $server = Servers::findOne($serverId);
                if ($server) {
                    $statsLink = $baseUrl . '/servers/' . $server->tag . '/' . $user->steam_id;
                }
            } else {
                // Если serverTag не передан, формируем ссылку на профиль пользователя
                $statsLink = $baseUrl . '/profile/' . $user->steam_id;
            }

            // Проверяем статус онлайн (если есть метод)
            $status = false;
            try {
                if (method_exists($user, 'isOnline')) {
                    $status = $user->isOnline();
                }
            } catch (\Throwable $e) {
                // Игнорируем ошибку
            }

            $items[] = [
                'id' => $user->id,
                'steam_id' => $user->steam_id,
                'name' => $user->username,
                'username' => $user->username,
                'avatar' => $avatar,
                'status' => $status,
                'statsLink' => $statsLink,
            ];
        }

        return $this->successResponse([
            'items' => $items,
        ]);
    }
}


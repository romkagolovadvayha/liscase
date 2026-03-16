<?php

namespace api\controllers\v1;

use common\components\payments\PaymentApi;
use common\components\queue\process\ActivatedDropJob;
use common\models\box\Category;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\box\DropDrop;
use common\models\invoice\Deposit;
use common\models\invoice\Invoice;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserDrop;
use Yii;
use yii\web\UnauthorizedHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Контроллер для работы с GameStoresRUST плагином
 * Формат API: POST запросы с методами как путями
 * URL: /v1/{method}?server_ip=XXX&server_port=YYY
 * Body: JSON с параметрами
 * Headers: serverIp, serverPort (IP и PORT берутся из ConVar.Server)
 * Авторизация: по steam_id из параметров запроса
 */
class GameStoresController extends BaseApiController
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
     * @return User
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

        /** @var User $user */
        $user = User::find()
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
                Yii::warning("Failed to update server_id for user {$user->id}: " . json_encode($user->getErrors()), 'gamestores');
            } else {
                Yii::info("Updated server_id for user {$user->id} from " . ($oldServerId ?? 'null') . " to {$server->id}", 'gamestores');
            }
        }

        return $user;
    }

    /**
     * Основной метод для обработки всех запросов GameStoresRUST
     *
     * @param string $method Метод API (baskets.item, baskets.bySteamId, etc.)
     * @return array
     */
    public function actionIndex($method)
    {

        // Получаем параметры из query string
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');

        // Получаем параметры из headers (если есть)
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');

        // Получаем body параметры (POST)
        // GameStoresRUST отправляет данные как form-data через UnityWebRequest.Post
        // Для методов без body (например, store.pluginInfo) POST может быть пустым
        $bodyParams = [];

        // Пробуем получить POST данные только если это не GET запрос
        if (Yii::$app->request->isPost) {
            $postData = Yii::$app->request->post();
            if (!empty($postData)) {
                $bodyParams = $postData;
            } else {
                // Если POST пустой, пробуем получить из raw body (JSON, если отправлено как JSON)
                $rawBody = Yii::$app->request->getRawBody();
                if (!empty($rawBody)) {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $bodyParams = $decoded;
                    }
                }
            }
        }

        // Находим сервер по IP и PORT из headers или query string
        // Приоритет: headers > query string
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;

        // Логирование для отладки
        if (empty($serverIp) || empty($serverPort)) {
            Yii::error("GameStores: Missing server IP or PORT. IP: {$serverIp}, PORT: {$serverPort}, Method: {$method}", 'gamestores');
        }

        $server = $this->findServer($serverIp, $serverPort);

        if (!$server) {
            Yii::error("GameStores: Server not found. IP: {$serverIp}, PORT: {$serverPort}, Method: {$method}", 'gamestores');
            return $this->errorResponseGameStores('Сервер с таким IP и PORT не найден', 103);
        }

        // Маршрутизация методов
        switch ($method) {
            case 'baskets.item':
                return $this->actionBasketsItem($bodyParams, $server);

            case 'baskets.bySteamId':
                return $this->actionBasketsBySteamId($bodyParams, $server);

            case 'baskets.makeIssued':
                return $this->actionBasketsMakeIssued($bodyParams, $server);

            case 'baskets.instantCommands':
                return $this->actionBasketsInstantCommands($server);

            case 'store.pluginInfo':
                return $this->actionStorePluginInfo($server);

            case 'server.helpInfo':
                return $this->actionServerHelpInfo($bodyParams, $server);

            case 'store.popularItems':
                return $this->actionStorePopularItems($bodyParams, $server);

            case 'store.buyAndTake':
                return $this->actionStoreBuyAndTake($bodyParams, $server);

            case 'wipeBlock.items':
                return $this->actionWipeBlockItems($server);

            case 'shop.categories':
                return $this->actionShopCategories($bodyParams, $server);

            case 'shop.products':
                return $this->actionShopProducts($bodyParams, $server);

            case 'shop.balance':
                return $this->actionShopBalance($bodyParams, $server);

            case 'shop.addToBasket':
                return $this->actionShopAddToBasket($bodyParams, $server);

            case 'shop.buy':
                return $this->actionShopBuy($bodyParams, $server);

            case 'shop.removeFromBasket':
                return $this->actionShopRemoveFromBasket($bodyParams, $server);

            case 'menubase.info':
                return $this->actionMenuBaseInfo($bodyParams, $server);

            case 'menubase.banners':
                return $this->actionMenuBaseBanners($bodyParams, $server);

            default:
                return $this->errorResponseGameStores('Метод не найден!', 105);
        }
    }

    /**
     * Обработка платежей через integrations/payments/custom
     * Используется для консольной команды gs.createpayment
     *
     * @return array
     */
    public function actionIntegrationsPaymentsCustom()
    {

        // Получаем параметры из query string
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');

        // Получаем параметры из headers (если есть)
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');

        // Получаем body параметры (POST)
        $bodyParams = Yii::$app->request->post();

        // Если POST пустой, пробуем получить из raw body (JSON, если отправлено как JSON)
        if (empty($bodyParams)) {
            $rawBody = Yii::$app->request->getRawBody();
            if (!empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $bodyParams = $decoded;
                }
            }
        }

        // Находим сервер по IP и PORT
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;

        $server = $this->findServer($serverIp, $serverPort);

        if (!$server) {
            return $this->errorResponseGameStores('Сервер с таким IP и PORT не найден', 103);
        }

        // Получаем параметры запроса
        $steamId = $bodyParams['steam_id'] ?? Yii::$app->request->post('steam_id');
        $amount = $bodyParams['amount'] ?? Yii::$app->request->post('amount');
        $methodName = $bodyParams['method_name'] ?? Yii::$app->request->post('method_name', 'Custom');
        $createPlayer = $bodyParams['create_player'] ?? Yii::$app->request->post('create_player', 'false');

        // Валидация параметров
        if (empty($steamId)) {
            return $this->errorResponseGameStores('Отсутствует обязательный параметр: steam_id', 400);
        }

        if (empty($amount)) {
            return $this->errorResponseGameStores('Отсутствует обязательный параметр: amount', 400);
        }

        $steamId = (string)$steamId;
        $amount = (int)$amount;

        // Проверка steam_id
        if (strlen($steamId) !== 17 || !is_numeric($steamId)) {
            return $this->errorResponseGameStores('Неверный формат steam_id', 400);
        }

        // Проверка суммы
        if ($amount < 1 || $amount > 1000000) {
            return $this->errorResponseGameStores('Сумма должна быть в диапазоне от 1 до 1000000', 400);
        }

        // Пытаемся получить пользователя по steam_id (авторизация)
        // Если пользователь не найден, создаем его (для платежей это допустимо)
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            // Если пользователь не найден, создаем его
            $user = User::findBySteamId($steamId, true, 'gamestores_payment');

            if (!$user) {
                return $this->errorResponseGameStores('Не удалось найти или создать пользователя', 400);
            }
            
            // Обновляем server_id для только что созданного пользователя
            if ($server && $user->server_id != $server->id) {
                $user->server_id = $server->id;
                $user->server_tag = $server->tag;
                $user->save(false);
            }
        }

        // Если create_player = true и пользователь только что создан, можно выполнить дополнительные действия
        // (например, начислить стартовый баланс)

        try {
            // Используем TYPE_PAYMENT_CARD_TINKOFF, как на сайте
            $paymentType = Deposit::TYPE_PAYMENT_CARD_TINKOFF;

            // Создаем операцию депозита
            $deposit = Deposit::createOperation($user->id, $amount, $paymentType);

            if (!$deposit) {
                return $this->errorResponseGameStores('Не удалось создать платеж', 500);
            }

            // Получаем API провайдера для Tinkoff и создаем платеж
            $paymentApi = PaymentApi::getInstance($paymentType);
            $response = $paymentApi->create($deposit);

            if (empty($response)) {
                $deposit->status = Deposit::STATUS_CANCELED;
                $deposit->save(false);
                return $this->errorResponseGameStores('Не удалось создать платеж в системе оплаты', 500);
            }

            // Получаем текущий баланс пользователя
            $user->refresh();
            $playerBalance = $user->balance ?? 0;

            // Получаем баланс магазина (если есть такая концепция)
            $storeBalance = 0; // TODO: реализовать получение баланса магазина, если необходимо

            // Формируем ответ в формате, ожидаемом плагином
            $result = [
                'result' => 'success',
                'data' => [
                    'payment_id' => $deposit->id,
                    'player_balance' => (string)$playerBalance,
                    'store_balance' => (string)$storeBalance,
                ],
            ];

            // Добавляем ссылку на оплату, если она есть
            if (!empty($response['paymentURL'])) {
                $result['data']['payment_url'] = $response['paymentURL'];
            }

            // Добавляем template данные, если они есть
            if (!empty($response['template'])) {
                $result['data']['template'] = $response['template'];
                $result['data']['template_data'] = $response;
            }

            return $result;

        } catch (\Exception $e) {
            Yii::error("Error creating payment for steam_id {$user->steam_id}: " . $e->getMessage(), 'gamestores');

            // Проверяем, не недостаточно ли средств на балансе магазина
            if (strpos($e->getMessage(), 'insufficient') !== false || strpos($e->getMessage(), 'баланс') !== false) {
                return [
                    'result' => 'fail',
                    'code' => '102',
                    'message' => 'Недостаточно средств на балансе магазина',
                ];
            }

            return $this->errorResponseGameStores('Ошибка при создании платежа: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Получить информацию о предмете в корзине
     * baskets.item
     * Body: {"basketId": 123, "steamId": "7656119..."}
     */
    private function actionBasketsItem($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }

        $basketId = $bodyParams['basketId'] ?? null;

        if (empty($basketId)) {
            return $this->errorResponseGameStores('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);

        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return $this->errorResponseGameStores('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности предмета
        if ($user->steam_id != $userDrop->user->steam_id) {
            return $this->errorResponseGameStores('Товар вам не принадлежит!', 107);
        }

        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;

        if (!$drop) {
            return $this->errorResponseGameStores('Предмет не найден', 107);
        }

        // Проверка, что rust_id не равен 0 (для предметов)
        // Для наборов (SET) и команд (COMMAND) rust_id может быть пустым
        if (empty($drop->command) 
            && $drop->drop_type !== Drop::TYPE_SET 
            && $drop->drop_type !== Drop::TYPE_COMMAND
            && (empty($drop->rust_id) || $drop->rust_id == 0)) {
            Yii::error("GameStores: Drop {$drop->id} has invalid rust_id: {$drop->rust_id}", 'gamestores');
            return $this->errorResponseGameStores('Предмет имеет неверный rust_id', 107);
        }

        // Получаем картинки для определения URL (если rust_id нет)
        $images = Drop::productsImages();
        $item = $this->formatItem($userDrop, $drop, $images, true);

        // Логирование для отладки
        Yii::info("GameStores baskets.item response for basketId {$basketId}: " . json_encode($item, JSON_UNESCAPED_UNICODE), 'gamestores');

        return $this->successResponseGameStores($item);
    }

    /**
     * Получить корзину игрока по Steam ID
     * baskets.bySteamId
     * Body: {"steamId": "7656119..."}
     */
    private function actionBasketsBySteamId($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }

        // Поддержка пагинации: page и limit из параметров запроса
        $page = (int)(Yii::$app->request->get('page') ?: Yii::$app->request->post('page') ?: 0);
        $limit = (int)(Yii::$app->request->get('limit') ?: Yii::$app->request->post('limit') ?: 49);
        
        // Ограничиваем максимум до 49 предметов (7x7) для оптимизации
        if ($limit > 49) {
            $limit = 49;
        }
        
        $offset = $page * $limit;

        /** @var UserDrop[] $userDrops */
        $userDrops = $user->getUserDrop()
            ->andWhere(['IN', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_WAIT]])
            ->orderBy(['id' => SORT_DESC])
            ->offset($offset)
            ->limit($limit)
            ->all();

        $data = [];
        $images = Drop::productsImages();
        $drops = Drop::getDropListAll();
        $itemsBlocked = DropBlocked::getBlockedList($server->id);

        Yii::info("actionBasketsBySteamId: Processing " . count($userDrops) . " items (page={$page}, limit={$limit}) for user {$user->steam_id}, serverId={$server->id}, serverTag={$server->tag}", 'gamestores');

        foreach ($userDrops as $userDrop) {
            $drop = $drops[$userDrop->drop_id] ?? null;
            if (!$drop) {
                Yii::warning("actionBasketsBySteamId: Drop not found for userDrop->drop_id={$userDrop->drop_id}", 'gamestores');
                continue;
            }

            $item = $this->formatBasketItem($userDrop, $drop, $images, $itemsBlocked, $server);
            $data[] = $item;
        }

        Yii::info("actionBasketsBySteamId: Returning " . count($data) . " items (page={$page}, limit={$limit}) for user {$user->steam_id}", 'gamestores');

        return $this->successResponseGameStores($data);
    }

    /**
     * Отметить предмет как выданный
     * baskets.makeIssued
     * Body: {"steamId": "7656119...", "basketId": 123}
     */
    private function actionBasketsMakeIssued($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }

        $basketId = $bodyParams['basketId'] ?? null;

        if (empty($basketId)) {
            return $this->errorResponseGameStores('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);

        // Логирование попытки выдачи предмета
        Yii::info("makeIssued: Attempt to issue item. basketId={$basketId}, userId={$user->id}, steamId={$user->steam_id}, serverId={$server->id}, serverTag={$server->tag}", 'gamestores');

        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            Yii::warning("makeIssued: Item already issued/sold. basketId={$basketId}, status={$userDrop->status}", 'gamestores');
            return $this->errorResponseGameStores('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности
        if ($user->steam_id != $userDrop->user->steam_id) {
            Yii::warning("makeIssued: Item ownership mismatch. basketId={$basketId}, userSteamId={$user->steam_id}, ownerSteamId={$userDrop->user->steam_id}", 'gamestores');
            return $this->errorResponseGameStores('Товар вам не принадлежит!', 107);
        }

        // Проверяем вайп блок - не позволяем выдавать заблокированные предметы
        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;
        
        if ($drop) {
            Yii::info("makeIssued: Checking wipe block. basketId={$basketId}, dropId={$drop->id}, dropName=" . Yii::t('database', $drop->name) . ", rustId={$drop->rust_id}, hasCommand=" . (!empty($drop->command) ? 'yes' : 'no'), 'gamestores');
            
            $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
            
            Yii::info("makeIssued: Wipe block check result. basketId={$basketId}, dropId={$drop->id}, isBlocked={$wipeBlockCheck['isBlocked']}, leftTime={$wipeBlockCheck['leftTime']}", 'gamestores');
            
            if ($wipeBlockCheck['isBlocked']) {
                Yii::warning("makeIssued: BLOCKED - Attempt to issue blocked item. basketId={$basketId}, dropId={$drop->id}, dropName=" . Yii::t('database', $drop->name) . ", rustId={$drop->rust_id}, leftTime={$wipeBlockCheck['leftTime']}, serverId={$server->id}", 'gamestores');
                return $this->errorResponseGameStores('Предмет временно заблокирован вайп блоком', 109);
            }
        } else {
            Yii::warning("makeIssued: Drop not found. basketId={$basketId}, dropId={$userDrop->drop_id}", 'gamestores');
        }
        
        $dropId = $drop ? $drop->id : 'unknown';
        $dropName = $drop ? Yii::t('database', $drop->name) : 'unknown';
        Yii::info("makeIssued: SUCCESS - Issuing item. basketId={$basketId}, dropId={$dropId}, dropName={$dropName}", 'gamestores');

        $userDrop->sended_at = date('Y-m-d H:i:s');
        $userDrop->status = UserDrop::STATUS_SENDED;
        $userDrop->save(); // Сохраняем статус в базу данных

        // Обработка статистики
        if (!empty($server) && !empty($userDrop->drop[0]->dropStat)) {
            $statistics = Statistics::find()
                ->andWhere(['steam_id' => $user->steam_id])
                ->andWhere(['server_tag' => $server->tag])
                ->andWhere(['wipe' => $server->currentWipe()])
                ->indexBy('key')
                ->all();

            foreach ($userDrop->drop[0]->dropStat as $dropStat) {
                if (empty($dropStat->value)) {
                    continue;
                }
                if (!empty($statistics[$dropStat->stat_key])) {
                    $statistics[$dropStat->stat_key]->value += $userDrop->count * $dropStat->value;
                    $statistics[$dropStat->stat_key]->save();
                } else {
                    $model = new Statistics();
                    $model->steam_id = $user->steam_id;
                    $model->server_tag = $server->tag;
                    $model->key = $dropStat->stat_key;
                    $model->value = $userDrop->count * $dropStat->value;
                    $model->wipe = $server->currentWipe();
                    $model->save();
                }
            }
        }

        // Обработка VIP товара
        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;
        if ($drop && $drop->drop_type === Drop::TYPE_VIP) {
            if ($server && $server->is_store == 1) {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                \common\models\user\UserVip::createOrExtend($userDrop->user_id, $expiresAt);

                if (!empty($drop->command)) {
                    $user = $userDrop->user;
                    if ($user) {
                        $command = str_replace('%STEAMID%', $user->steam_id, $drop->command);
                        \common\models\rcon\RconTasks::execute($command);
                    }
                }
            }
        }

        \Yii::$app->queueProcess->push(new ActivatedDropJob(['userDrop' => $userDrop]));

        return $this->successResponseGameStores(null);
    }

    /**
     * Получить список автоматических команд
     * baskets.instantCommands
     * Body: пустой
     */
    private function actionBasketsInstantCommands($server)
    {
        // В текущей реализации всегда возвращаем пустой список
        // Можно добавить логику получения команд из очереди
        return $this->successResponseGameStores([]);
    }

    /**
     * Получить информацию о магазине
     * store.pluginInfo
     * Body: пустой
     */
    private function actionStorePluginInfo($server)
    {
        // Плагин ожидает формат: {"result": "success", "data": {...}}
        $data = [
            'link' => Yii::$app->settings->get('site_domain'),
            'defaultBalance' => 50, // camelCase, как ожидает плагин
            'servers' => [(string)$server->id], // Список строк, как ожидает плагин
        ];

        // Возвращаем в формате, который ожидает плагин
        return [
            'result' => 'success',
            'data' => $data,
        ];
    }

    /**
     * Получить всю информацию для раздела помощи (вайпы, команды)
     * server.helpInfo
     * Body: пустой (универсальный метод, не требует авторизации)
     */
    private function actionServerHelpInfo($bodyParams, $server)
    {
        $result = [];

        // 1. Информация о вайпах
        $result['wipeInfo'] = $this->getWipeInfo($server);

        // 2. Команды сервера (возвращаем напрямую массив, не оборачивая в successResponseGameStores)
        $result['commands'] = $this->getServerCommands($server);

        return $this->successResponseGameStores($result);
    }

    /**
     * Получить информацию о вайпах сервера (вспомогательный метод)
     */
    private function getWipeInfo($server)
    {
        $tz = new \DateTimeZone(Yii::$app->timeZone ?: 'UTC');

        // Получаем дату последнего вайпа из поля wipe сервера
        $lastWipeDate = null;
        if (!empty($server->wipe)) {
            try {
                $wipeTimestamp = strtotime($server->wipe);
                if ($wipeTimestamp !== false) {
                    $lastWipeDate = new \DateTimeImmutable('@' . $wipeTimestamp, $tz);
                }
            } catch (\Exception $e) {
                // Если не удалось распарсить, оставляем null
            }
        }

        // Получаем следующий вайп из поля next_wipe сервера
        $nextWipeDate = null;
        if (!empty($server->next_wipe)) {
            try {
                $nextWipeTimestamp = strtotime($server->next_wipe);
                if ($nextWipeTimestamp !== false) {
                    $nextWipeDate = new \DateTimeImmutable('@' . $nextWipeTimestamp, $tz);
                }
            } catch (\Exception $e) {
                // Если не удалось распарсить, оставляем null
            }
        }

        // Получаем глобальный вайп из поля global_wipe сервера
        $nextGlobalWipeDate = null;
        if (!empty($server->global_wipe)) {
            try {
                $globalWipeTimestamp = strtotime($server->global_wipe);
                if ($globalWipeTimestamp !== false) {
                    $nextGlobalWipeDate = new \DateTimeImmutable('@' . $globalWipeTimestamp, $tz);
                }
            } catch (\Exception $e) {
                // Если не удалось распарсить, оставляем null
            }
        }

        // Форматируем даты в формат 01.12.2025 16:00 МСК
        $formatDate = function($date) use ($tz) {
            if (!$date) return null;
            $moscowTz = new \DateTimeZone('Europe/Moscow');
            $dateMoscow = $date->setTimezone($moscowTz);
            return $dateMoscow->format('d.m.Y H:i') . ' МСК';
        };

        return [
            'lastWipe' => $formatDate($lastWipeDate),
            'nextWipe' => $formatDate($nextWipeDate),
            'nextGlobalWipe' => $formatDate($nextGlobalWipeDate),
        ];
    }

    /**
     * Получить команды сервера из раздела правил (вспомогательный метод)
     */
    private function getServerCommands($server)
    {
        $commands = [];

        // Ищем категорию "Команды сервера"
        $commandsCategory = \common\models\servers\ServersRulesCategory::find()
            ->where(['name' => 'Команды на сервере'])
            ->one();

        if ($commandsCategory) {
            // Получаем все правила для сервера
            $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);

            // Фильтруем правила по категории "Команды сервера"
            foreach ($rules as $rule) {
                if ($rule->category_id == $commandsCategory->id) {
                    // Извлекаем команды из content (может быть HTML)
                    $content = strip_tags($rule->content); // Убираем HTML теги
                    $content = trim($content);

                    if (!empty($content)) {
                        // Если content содержит несколько строк, разбиваем их
                        $lines = explode("\n", $content);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                // Если строка содержит ":", используем формат "команда: описание"
                                if (strpos($line, ':') !== false) {
                                    $commands[] = ['command' => $line];
                                } else {
                                    // Иначе просто команда
                                    $commands[] = ['command' => $line];
                                }
                            }
                        }
                    }
                }
            }
        }

        // Добавляем категорию "Команды сервера"
        $data = [
            [
                'category' => 'Команды сервера',
                'commands' => $commands,
            ],
        ];

        // Если есть админка, добавляем её тоже
        if (!empty($server->admin_url)) {
            $data[] = [
                'category' => 'Админка',
                'url' => $server->admin_url,
            ];
        }

        // Возвращаем напрямую массив, не оборачивая в successResponseGameStores
        return $data;
    }

    /**
     * Первый N-й день недели месяца: $weekday 1=Пн..7=Вс
     */
    private function firstWeekdayOfMonth(\DateTimeImmutable $monthStart, $weekday)
    {
        $firstN = (int)$monthStart->format('N');
        $delta  = (int)$weekday - $firstN;
        if ($delta < 0) $delta += 7;
        return $monthStart->modify('+' . $delta . ' day');
    }

    /**
     * Найти сервер по IP и PORT
     * Идентификация происходит только по IP и PORT сервера
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
     * Проверить, заблокирован ли предмет вайп блоком
     * 
     * @param Drop $drop Предмет
     * @param Servers $server Сервер
     * @return array ['isBlocked' => bool, 'leftTime' => float] Результат проверки
     */
    private function checkWipeBlock($drop, $server)
    {
        $result = [
            'isBlocked' => false,
            'leftTime' => 0,
        ];

        // Проверяем только для предметов (не команд) и только если есть rust_id
        if (empty($drop->command) && !empty($drop->rust_id) && $drop->rust_id > 0 && $server) {
            // Определяем, является ли предмет blueprint
            // Blueprint имеет itemid = -1580979675 (ItemManager.blueprintBaseDef.itemid)
            $isBlueprint = ($drop->rust_id == -1580979675);
            
            // Сначала проверяем кэш вайп блока (заполняется плагином GameStoresWipeBlock)
            $cacheKey = "wipe_block_left_time_{$server->id}_{$drop->id}_{$drop->rust_id}_" . ($isBlueprint ? '1' : '0');
            $leftTime = Yii::$app->cache->get($cacheKey);
            
            // Если в кэше нет данных, проверяем таблицу drop_blocked (как во фронтенде)
            if ($leftTime === false || $leftTime <= 0) {
                $blockedAt = \common\models\box\DropBlocked::getBlocked($drop->id, $server->id);
                if (!empty($blockedAt)) {
                    $blockedTimestamp = strtotime($blockedAt);
                    $currentTimestamp = time();
                    if ($blockedTimestamp > $currentTimestamp) {
                        // Предмет заблокирован, вычисляем оставшееся время в секундах
                        $leftTime = $blockedTimestamp - $currentTimestamp;
                        Yii::info("checkWipeBlock: Found in drop_blocked table. dropId={$drop->id}, rustId={$drop->rust_id}, blockedAt={$blockedAt}, leftTime={$leftTime}", 'gamestores');
                    } else {
                        // Блокировка истекла
                        $leftTime = 0;
                    }
                } else {
                    $leftTime = 0;
                }
            }
            
            Yii::info("checkWipeBlock: dropId={$drop->id}, rustId={$drop->rust_id}, isBlueprint=" . ($isBlueprint ? 'yes' : 'no') . ", cacheKey={$cacheKey}, leftTime={$leftTime}", 'gamestores');
            
            if ($leftTime > 0) {
                $result['isBlocked'] = true;
                $result['leftTime'] = $leftTime;
                Yii::info("checkWipeBlock: ITEM IS BLOCKED. dropId={$drop->id}, rustId={$drop->rust_id}, leftTime={$leftTime}", 'gamestores');
            } else {
                Yii::info("checkWipeBlock: Item is NOT blocked. dropId={$drop->id}, rustId={$drop->rust_id}", 'gamestores');
            }
        } else {
            $reason = '';
            if (!empty($drop->command)) {
                $reason = 'is_command';
            } elseif (empty($drop->rust_id) || $drop->rust_id <= 0) {
                $reason = 'no_rust_id';
            } elseif (!$server) {
                $reason = 'no_server';
            }
            Yii::info("checkWipeBlock: Skipping wipe block check. dropId={$drop->id}, reason={$reason}", 'gamestores');
        }

        return $result;
    }

    /**
     * Форматировать предмет для baskets.item
     */
    private function formatItem($userDrop, $drop, $images = [], $includeSubDrop = false)
    {
        // Логирование для отладки
        Yii::info("formatItem: userDrop->drop_id={$userDrop->drop_id}, drop->id={$drop->id}, drop->rust_id={$drop->rust_id}, drop->name=" . Yii::t('database', $drop->name), 'gamestores');
        
        $item = [
            'id' => $userDrop->id,
            'basketId' => $userDrop->id, // Для совместимости
            'productId' => (string)$drop->id, // ID продукта (drop)
            'amount' => $userDrop->count,
            'name' => $drop->name,
            'lvl_inspection' => 0,
            'full_only' => $drop->full_only,
            'is_blocked_building' => $drop->is_blocked_building,
            'subDrop' => [],
        ];

        // Плагин ожидает вложенную структуру data["data"] с itemId или commands
        $data = [];

        if (!empty($drop->command)) {
            // Команда - нет rust_id, используем картинку с сайта
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;

            // Для команд плагин ожидает data["data"]["commands"] как массив
            $commands = explode("\n", $drop->command);
            $commands = array_filter(array_map('trim', $commands)); // Убираем пустые строки
            $data['commands'] = array_values($commands); // Преобразуем в массив с числовыми ключами

            // Для команд используем картинку с сайта
            $item['img'] = $images[$userDrop->drop_id]['150px'] ?? '';
        } else {
            // Предмет
            $item['type'] = "item";
            $rustId = $drop->rust_id ?? 0;
            $item['item_id'] = $rustId;
            $item['itemId'] = $rustId; // Добавляем itemId в корень для совместимости

            // Для предметов плагин ожидает data["data"]["itemId"]
            // Всегда передаем itemId (даже если 0), чтобы плагин мог обработать
            $data['itemId'] = $rustId;

            // Также добавляем itemDefinition.itemid для совместимости (только если rust_id валидный)
            if (!empty($drop->rust_id) && $drop->rust_id > 0) {
                $data['itemDefinition'] = [
                    'itemid' => $drop->rust_id
                ];

                // Если есть rust_id, используем его как идентификатор для получения картинки из игры
                // Плагин проверяет: если img не содержит "http", то это rust_id
                $item['img'] = (string)$drop->rust_id;
            } else {
                // Если rust_id нет или равен 0, используем картинку с сайта
                // Плагин вернет IsValid = false, так как ItemID == 0
                $item['img'] = $images[$userDrop->drop_id]['150px'] ?? '';
            }
        }

        // Добавляем вложенную структуру data
        $item['data'] = $data;

        if ($includeSubDrop && $drop->full_only) {
            foreach ($drop->subDrops as $subDrop) {
                $_subDrop = [];
                $_subDrop['count'] = $subDrop->count;
                if (!empty($subDrop->drop->command)) {
                    $_subDrop['command'] = str_replace("\r", '', $subDrop->drop->command);
                    $_subDrop['type'] = "command";
                    $_subDrop['item_id'] = 0;
                } else {
                    $_subDrop['type'] = "item";
                    $_subDrop['item_id'] = $subDrop->drop->rust_id;
                }
                $item['subDrop'][] = $_subDrop;
            }
        }

        return $item;
    }

    /**
     * Форматировать предмет для baskets.bySteamId
     */
    private function formatBasketItem($userDrop, $drop, $images, $itemsBlocked, $server = null)
    {
        // Логирование для отладки
        Yii::info("formatBasketItem: basketId={$userDrop->id}, dropId={$drop->id}, rustId={$drop->rust_id}, hasCommand=" . (!empty($drop->command) ? 'yes' : 'no') . ", serverId=" . ($server ? $server->id : 'null'), 'gamestores');
        
        // Определяем картинку: если есть rust_id, используем его, иначе картинку с сайта
        $img = '';
        if (!empty($drop->command)) {
            // Команда - нет rust_id, используем картинку с сайта
            $img = $images[$userDrop->drop_id]['150px'] ?? '';
        } else {
            // Предмет: если есть rust_id, используем его как идентификатор
            if (!empty($drop->rust_id)) {
                $img = (string)$drop->rust_id;
            } else {
                // Если rust_id нет, используем картинку с сайта
                $img = $images[$userDrop->drop_id]['150px'] ?? '';
            }
        }

        // Убеждаемся, что img всегда строка (не null)
        if ($img === null) {
            $img = '';
        }

        // Убеждаемся, что amount всегда число (не null)
        $amount = $userDrop->count ?? 0;

        // Рассчитываем цену используя RealPrice
        $realPrice = $drop->getRealPrice(true);
        
        $item = [
            'id' => $userDrop->id,
            'basketId' => $userDrop->id, // Для совместимости
            'productId' => (string)$drop->id, // ID продукта (drop)
            'amount' => $amount,
            'quantity' => $amount, // Для совместимости с LShop
            'name' => $drop->name,
            'price' => $realPrice, // Используем RealPrice
            'img' => $img,
            'image' => $img, // Для совместимости с LShop
            'blocked' => false,
            'block_date' => null,
            'kd' => false,
            'full_only' => $drop->full_only,
            'is_blocked_building' => $drop->is_blocked_building,
            'subDrop' => [],
        ];

        if (!empty($drop->blocked_hour)) {
            if (!empty($itemsBlocked[$userDrop->drop_id])) {
                $item['blocked'] = true;
                $item['block_date'] = strtotime($itemsBlocked[$userDrop->drop_id]);
            }
        }

        // Проверка вайп блока (как в плагине и на фронтенде)
        if ($server) {
            $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
            $item['isBlocked'] = $wipeBlockCheck['isBlocked'];
            if ($wipeBlockCheck['isBlocked']) {
                $item['leftTime'] = $wipeBlockCheck['leftTime'];
                Yii::info("formatBasketItem: ITEM IS BLOCKED. basketId={$userDrop->id}, dropId={$drop->id}, rustId={$drop->rust_id}, leftTime={$wipeBlockCheck['leftTime']}", 'gamestores');
            } else {
                $item['isBlocked'] = false;
                $item['leftTime'] = 0;
                Yii::info("formatBasketItem: Item is NOT blocked. basketId={$userDrop->id}, dropId={$drop->id}, rustId={$drop->rust_id}", 'gamestores');
            }
        } else {
            $item['isBlocked'] = false;
            $item['leftTime'] = 0;
            Yii::warning("formatBasketItem: Server is NULL. basketId={$userDrop->id}, dropId={$drop->id}, rustId={$drop->rust_id}", 'gamestores');
        }

        if ($drop->full_only) {
            foreach ($drop->subDrops as $subDrop) {
                $_subDrop = [];
                if (!empty($subDrop->drop->command)) {
                    $_subDrop['command'] = str_replace("\r", '', $subDrop->drop->command);
                    $_subDrop['type'] = "command";
                    $_subDrop['item_id'] = 0;
                } else {
                    $_subDrop['type'] = "item";
                    $_subDrop['item_id'] = $subDrop->drop->rust_id;
                }
                $_subDrop['count'] = $subDrop->count;
                $item['subDrop'][] = $_subDrop;
            }
        }

        // Плагин ожидает вложенную структуру data["data"] с itemId или commands
        $data = [];

        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;

            // Для команд плагин ожидает data["data"]["commands"] как массив
            $commands = explode("\n", $drop->command);
            $commands = array_filter(array_map('trim', $commands)); // Убираем пустые строки
            $data['commands'] = array_values($commands); // Преобразуем в массив с числовыми ключами
        } else {
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id ?? 0;
            $item['itemId'] = $drop->rust_id ?? 0; // Для совместимости с LShop

            // Для предметов плагин ожидает data["data"]["itemId"]
            // Всегда передаем itemId (даже если 0), чтобы плагин мог обработать
            $rustId = $drop->rust_id ?? 0;
            $data['itemId'] = $rustId;

            // Также добавляем itemDefinition.itemid для совместимости (только если rust_id валидный)
            if (!empty($drop->rust_id)) {
                $data['itemDefinition'] = [
                    'itemid' => $drop->rust_id
                ];
            }
        }

        // Добавляем вложенную структуру data
        $item['data'] = $data;

        return $item;
    }

    /**
     * Успешный ответ (совместимость со старым форматом плагина)
     */
    private function successResponseGameStores($data)
    {
        return [
            'result' => 'success',
            'code' => 100,
            'data' => $data,
        ];
    }

    /**
     * Ответ с ошибкой (совместимость со старым форматом плагина)
     */
    private function errorResponseGameStores($message, $code)
    {
        // Устанавливаем HTTP статус код в зависимости от кода ошибки
        if ($code == 105) {
            // Ошибка авторизации или пользователь не найден
            Yii::$app->response->statusCode = 401;
        } elseif ($code >= 500) {
            Yii::$app->response->statusCode = 500;
        } else {
            Yii::$app->response->statusCode = 400;
        }

        return [
            'result' => 'fail',
            'message' => $message,
            'code' => $code,
        ];
    }

    /**
     * Получить список популярных товаров для мгновенной покупки
     * store.popularItems
     * Body: {"dropIds": [1, 2, 3]} (опционально, если не указан - вернет 8 случайных товаров)
     */
    private function actionStorePopularItems($bodyParams, $server)
    {
        $dropIds = $bodyParams['dropIds'] ?? $bodyParams['drop_ids'] ?? null;
        
        $drops = Drop::getDropListAll();
        $images = Drop::productsImages();
        $itemsBlocked = DropBlocked::getBlockedList($server->id);
        
        $popularDrops = [];
        
        if (!empty($dropIds) && is_array($dropIds)) {
            // Если указаны ID, возвращаем эти товары
            foreach ($dropIds as $dropId) {
                $drop = $drops[$dropId] ?? null;
                if ($drop && $drop->status == Drop::STATUS_ACTIVE && $drop->market_status == Drop::MARKET_STATUS_ACTIVE) {
                    // Проверяем вайп блок - не возвращаем заблокированные предметы
                    $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
                    if ($wipeBlockCheck['isBlocked']) {
                        continue; // Пропускаем заблокированные предметы
                    }
                    
                    // Форматируем как товар для корзины, но без UserDrop
                    $item = $this->formatPopularItem($drop, $images, $itemsBlocked, $server);
                    $popularDrops[] = $item;
                }
            }
        } else {
            // Если ID не указаны, возвращаем товары с фиксированными drop ID для моментальной покупки
            $defaultDropIds = [477, 599, 107, 320, 295, 305, 316, 148];
            
            foreach ($defaultDropIds as $dropId) {
                $drop = $drops[$dropId] ?? null;
                if ($drop && $drop->status == Drop::STATUS_ACTIVE && $drop->market_status == Drop::MARKET_STATUS_ACTIVE) {
                    // Проверяем вайп блок - не возвращаем заблокированные предметы
                    $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
                    if ($wipeBlockCheck['isBlocked']) {
                        continue; // Пропускаем заблокированные предметы
                    }
                    
                    // Форматируем как товар для корзины, но без UserDrop
                    $item = $this->formatPopularItem($drop, $images, $itemsBlocked, $server);
                    $popularDrops[] = $item;
                }
            }
        }
        
        return $this->successResponseGameStores($popularDrops);
    }

    /**
     * Купить и выдать товар (мгновенная покупка)
     * store.buyAndTake
     * Body: {"steamId": "7656119...", "dropId": 123, "quantity": 1}
     */
    private function actionStoreBuyAndTake($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }

        $dropId = $bodyParams['dropId'] ?? $bodyParams['drop_id'] ?? null;
        $quantity = isset($bodyParams['quantity']) ? (int)$bodyParams['quantity'] : 1;

        if (empty($dropId)) {
            return $this->errorResponseGameStores('Отсутствует параметр dropId', 105);
        }

        if ($quantity < 1 || $quantity > 100) {
            return $this->errorResponseGameStores('Количество должно быть от 1 до 100', 105);
        }

        $drops = Drop::getDropListAll();
        
        // Логирование для отладки - проверяем тип dropId и ключи в массиве
        Yii::info("buyAndTake: Request dropId={$dropId} (type: " . gettype($dropId) . "), drops array keys sample: " . implode(', ', array_slice(array_keys($drops), 0, 10)), 'gamestores');
        
        // Пробуем найти drop по разным типам ключа
        $drop = null;
        if (isset($drops[$dropId])) {
            $drop = $drops[$dropId];
        } elseif (isset($drops[(int)$dropId])) {
            $drop = $drops[(int)$dropId];
        } elseif (isset($drops[(string)$dropId])) {
            $drop = $drops[(string)$dropId];
        }
        
        if (!$drop || $drop->status != Drop::STATUS_ACTIVE || $drop->market_status != Drop::MARKET_STATUS_ACTIVE) {
            Yii::error("buyAndTake: Drop not found or inactive. dropId={$dropId}, drop found: " . ($drop ? "yes (id={$drop->id})" : "no"), 'gamestores');
            return $this->errorResponseGameStores('Товар не найден или недоступен для покупки', 107);
        }
        
        // Проверяем вайп блок - не позволяем покупать заблокированные предметы
        $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
        if ($wipeBlockCheck['isBlocked']) {
            Yii::warning("buyAndTake: Attempt to buy blocked item. dropId={$dropId}, leftTime={$wipeBlockCheck['leftTime']}", 'gamestores');
            return $this->errorResponseGameStores('Предмет временно заблокирован вайп блоком', 109);
        }
        
        // Логирование для отладки
        Yii::info("buyAndTake: Request dropId={$dropId}, found drop->id={$drop->id}, drop->rust_id={$drop->rust_id}, drop->name=" . Yii::t('database', $drop->name), 'gamestores');

        // Рассчитываем цену
        $basePrice = $drop->price - ($drop->price * ($drop->discount ?? 0) / 100);
        $pricePerItem = ceil($basePrice);
        $totalPrice = $pricePerItem * $quantity;

        // Проверяем баланс
        $balance = $user->getPersonalBalance();
        if ($totalPrice > $balance->balanceCeil) {
            return $this->errorResponseGameStores('Недостаточно средств на счете', 108);
        }

        // Начинаем транзакцию
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем Invoice для списания средств
            $comment = Yii::t('common', 'Мгновенная покупка предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ]);
            
            Invoice::createRecord(
                $user->id,
                $totalPrice,
                Invoice::TYPE_PAYMENT_MARKET_DROP,
                null, // box_id
                null, // sets_id
                $drop->id, // drop_id
                $comment
            );

            // Создаем UserDrop сразу со статусом STATUS_SENDED, чтобы товар не попадал в корзину
            // Для мгновенной покупки товар должен быть сразу выданным
            $sendedAt = date('Y-m-d H:i:s');
            $userDropIds = [];
            if ($drop->drop_type == 2) {
                // TYPE_SET - создаем записи для всех subDrops
                $subDrops = DropDrop::find()
                    ->where(['parent_drop_id' => $drop->id])
                    ->with('drop')
                    ->all();
                
                foreach ($subDrops as $subDropRelation) {
                    if ($subDropRelation->drop) {
                        $subDropCount = ($subDropRelation->count ?? 1) * $quantity;
                        $userDrop = UserDrop::createRecord(
                            $user->id,
                            $subDropRelation->drop_id,
                            null, // box_id
                            null, // sets_id
                            UserDrop::STATUS_SENDED, // Сразу выданный статус
                            false, // auto
                            $subDropCount, // count
                            null, // created_at
                            $drop->id // parent_drop_id
                        );
                        // Устанавливаем sended_at сразу после создания
                        $userDrop->sended_at = $sendedAt;
                        $userDrop->save(false);
                        $userDropIds[] = $userDrop->id;
                    }
                }
            } else {
                // Обычный товар - создаем запись
                $dropCount = ($drop->count ?? 1) * $quantity;
                $userDrop = UserDrop::createRecord(
                    $user->id,
                    $drop->id,
                    null, // box_id
                    null, // sets_id
                    UserDrop::STATUS_SENDED, // Сразу выданный статус
                    false, // auto
                    $dropCount, // count
                    null, // created_at
                    null // parent_drop_id
                );
                // Устанавливаем sended_at сразу после создания
                $userDrop->sended_at = $sendedAt;
                $userDrop->save(false);
                $userDropIds[] = $userDrop->id;
            }

            // Пересчитываем баланс
            $balance->recalculateBalance();
            $newBalance = $balance->balanceCeil;

            // Коммитим транзакцию
            $dbTransaction->commit();

            // Получаем созданные UserDrop для выдачи
            // Для наборов выдаем все созданные UserDrop, для обычных товаров - один
            $userDrops = [];
            foreach ($userDropIds as $id) {
                $userDrop = UserDrop::findOne($id);
                if ($userDrop) {
                    $userDrops[] = $userDrop;
                }
            }

            if (empty($userDrops)) {
                return $this->errorResponseGameStores('Ошибка создания записи товара', 500);
            }

            // Выдаем все товары (для наборов - все subDrops, для обычных - один товар)
            // Товары уже созданы со статусом STATUS_SENDED, поэтому они не попадут в корзину
            $items = [];
            foreach ($userDrops as $userDrop) {
                // Товар уже имеет статус STATUS_SENDED и sended_at установлен

                // Обработка статистики (только для основного товара, не для subDrops)
                if ($userDrop->parent_drop_id === null && !empty($server) && !empty($drop->dropStat)) {
                    $statistics = Statistics::find()
                        ->andWhere(['steam_id' => $user->steam_id])
                        ->andWhere(['server_tag' => $server->tag])
                        ->andWhere(['wipe' => $server->currentWipe()])
                        ->indexBy('key')
                        ->all();

                    foreach ($drop->dropStat as $dropStat) {
                        if (empty($dropStat->value)) {
                            continue;
                        }
                        if (!empty($statistics[$dropStat->stat_key])) {
                            $statistics[$dropStat->stat_key]->value += $userDrop->count * $dropStat->value;
                            $statistics[$dropStat->stat_key]->save();
                        } else {
                            $model = new Statistics();
                            $model->steam_id = $user->steam_id;
                            $model->server_tag = $server->tag;
                            $model->key = $dropStat->stat_key;
                            $model->value = $userDrop->count * $dropStat->value;
                            $model->wipe = $server->currentWipe();
                            $model->save();
                        }
                    }
                }

                // Обработка VIP товара (только для основного товара)
                if ($userDrop->parent_drop_id === null && $drop->drop_type === Drop::TYPE_VIP) {
                    if ($server && $server->is_store == 1) {
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                        \common\models\user\UserVip::createOrExtend($userDrop->user_id, $expiresAt);

                        if (!empty($drop->command)) {
                            $command = str_replace('%STEAMID%', $user->steam_id, $drop->command);
                            \common\models\rcon\RconTasks::execute($command);
                        }
                    }
                }

                // Добавляем в очередь для обработки
                \Yii::$app->queueProcess->push(new ActivatedDropJob(['userDrop' => $userDrop]));

                // Форматируем ответ с информацией о выданном товаре
                $images = Drop::productsImages();
                
                // ВАЖНО: Для обычных товаров всегда используем $drop (который был куплен)
                // Для наборов (subDrops) используем товар из $drops по userDrop->drop_id
                if ($userDrop->parent_drop_id === null) {
                    // Обычный товар - используем $drop, который был куплен
                    $userDropDrop = $drop;
                } else {
                    // SubDrop из набора - используем товар из $drops
                    $userDropDrop = $drops[$userDrop->drop_id] ?? $drop;
                }
                
                // Логирование для отладки
                Yii::info("buyAndTake: dropId={$dropId}, userDrop->drop_id={$userDrop->drop_id}, userDrop->parent_drop_id={$userDrop->parent_drop_id}, userDropDrop->id={$userDropDrop->id}, userDropDrop->rust_id={$userDropDrop->rust_id}, userDropDrop->name=" . Yii::t('database', $userDropDrop->name), 'gamestores');
                
                $item = $this->formatItem($userDrop, $userDropDrop, $images, true);
                $items[] = $item;
            }

            return $this->successResponseGameStores([
                'items' => $items,
                'newBalance' => (string)$newBalance,
            ]);

        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            Yii::error("Error in buyAndTake for steam_id {$user->steam_id}, drop_id {$dropId}: " . $e->getMessage(), 'gamestores');
            return $this->errorResponseGameStores('Ошибка при покупке товара: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Форматировать товар для списка популярных товаров (без UserDrop)
     */
    private function formatPopularItem($drop, $images, $itemsBlocked, $server = null)
    {
        // Определяем картинку
        $img = '';
        if (!empty($drop->command)) {
            // Команда - используем картинку с сайта
            $img = $images[$drop->id]['150px'] ?? '';
        } else {
            // Предмет: если есть rust_id, используем его как идентификатор
            if (!empty($drop->rust_id)) {
                $img = (string)$drop->rust_id;
            } else {
                // Если rust_id нет, используем картинку с сайта
                $img = $images[$drop->id]['150px'] ?? '';
            }
        }

        if ($img === null) {
            $img = '';
        }

        // Рассчитываем цену
        $basePrice = $drop->price - ($drop->price * ($drop->discount ?? 0) / 100);
        $price = ceil($basePrice);

        $item = [
            'id' => $drop->id,
            'productId' => (string)$drop->id,
            'amount' => $drop->count ?? 1,
            'name' => $drop->name,
            'img' => $img,
            'price' => $price,
            'blocked' => false,
            'block_date' => null,
            'full_only' => $drop->full_only,
            'is_blocked_building' => $drop->is_blocked_building,
            'subDrop' => [],
        ];

        // Проверка блокировки по часам
        if (!empty($drop->blocked_hour)) {
            if (!empty($itemsBlocked[$drop->id])) {
                $item['blocked'] = true;
                $item['block_date'] = strtotime($itemsBlocked[$drop->id]);
            }
        }
        
        // Проверка вайп блока (как в плагине и на фронтенде)
        $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
        $item['isBlocked'] = $wipeBlockCheck['isBlocked'];
        if ($wipeBlockCheck['isBlocked']) {
            $item['leftTime'] = $wipeBlockCheck['leftTime'];
        }

        // Добавляем subDrop для наборов
        if ($drop->full_only) {
            foreach ($drop->subDrops as $subDrop) {
                $_subDrop = [];
                if (!empty($subDrop->drop->command)) {
                    $_subDrop['command'] = str_replace("\r", '', $subDrop->drop->command);
                    $_subDrop['type'] = "command";
                    $_subDrop['item_id'] = 0;
                } else {
                    $_subDrop['type'] = "item";
                    $_subDrop['item_id'] = $subDrop->drop->rust_id;
                }
                $_subDrop['count'] = $subDrop->count;
                $item['subDrop'][] = $_subDrop;
            }
        }

        // Плагин ожидает вложенную структуру data["data"] с itemId или commands
        $data = [];

        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;

            $commands = explode("\n", $drop->command);
            $commands = array_filter(array_map('trim', $commands));
            $data['commands'] = array_values($commands);
        } else {
            $item['type'] = "item";
            $rustId = $drop->rust_id ?? 0;
            $item['item_id'] = $rustId;
            $item['itemId'] = $rustId; // Добавляем itemId в корень для совместимости

            $data['itemId'] = $rustId;

            if (!empty($drop->rust_id)) {
                $data['itemDefinition'] = [
                    'itemid' => $drop->rust_id
                ];
            }
        }

        $item['data'] = $data;

        return $item;
    }

    /**
     * Получить информацию о вайп-блоке предметов
     * wipeBlock.items
     * Возвращает предметы, сгруппированные по blocked_hour
     */
    private function actionWipeBlockItems($server)
    {
        // Получаем все предметы с blocked_hour, отсортированные по blocked_hour
        $drops = Drop::find()
            ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
            ->andWhere('blocked_hour IS NOT NULL')
            ->orderBy(['blocked_hour' => SORT_ASC])
            ->all();

        // Получаем изображения размером 150px для всех предметов (как в других местах)
        $images = Drop::productsImages();
        
        // Получаем реальное время разблокировки из таблицы drop_blocked для этого сервера
        $blockedList = \common\models\box\DropBlocked::getBlockedList($server->id, true);

        // Группируем по blocked_hour
        $results = [];
        foreach ($drops as $drop) {
            $blockedHour = $drop->blocked_hour;
            if (empty($results[$blockedHour])) {
                $results[$blockedHour] = [];
            }
            
            // Формируем информацию о предмете
            // Используем images[$drop->id]['150px'] для получения изображения размером drop150 (как в других местах)
            $imageUrl = $images[$drop->id]['150px'] ?? '';
            // Если нет 150px, используем оригинальное изображение
            if (empty($imageUrl) && $drop->imageOrig) {
                $imageUrl = $drop->imageOrig->getImagePubUrl();
            }
            
            // Получаем реальное время разблокировки из drop_blocked, если оно есть
            $blockedAt = $blockedList[$drop->id] ?? null;
            
            $item = [
                'id' => $drop->id,
                'productId' => (string)$drop->id,
                'name' => $drop->name,
                'rust_id' => $drop->rust_id ?? '',
                'item_id' => $drop->rust_id ?? '',
                'blocked_hour' => $blockedHour,
                'image' => $imageUrl,
            ];
            
            // Добавляем время окончания блокировки, если предмет заблокирован
            // Конвертируем из московского времени (MSK) в UTC для плагина
            if ($blockedAt) {
                try {
                    // Парсим время как московское (MSK, UTC+3)
                    $moscowTz = new \DateTimeZone('Europe/Moscow');
                    $blockedAtMoscow = new \DateTime($blockedAt, $moscowTz);
                    // Конвертируем в UTC
                    $blockedAtMoscow->setTimezone(new \DateTimeZone('UTC'));
                    $item['blocked_at'] = $blockedAtMoscow->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // Если не удалось распарсить, отправляем как есть
                    $item['blocked_at'] = $blockedAt;
                    Yii::warning("Failed to convert blocked_at to UTC: " . $e->getMessage(), 'gamestores');
                }
            }
            
            $results[$blockedHour][] = $item;
        }

        // Преобразуем в формат, удобный для плагина
        $formattedResults = [];
        foreach ($results as $blockedHour => $items) {
            // Находим максимальное время окончания блокировки для группы (если есть заблокированные предметы)
            $maxBlockedAt = null;
            $maxBlockedAtTimestamp = null;
            $currentTimestamp = time();
            
            foreach ($items as $item) {
                if (isset($item['blocked_at'])) {
                    $blockedAtTimestamp = strtotime($item['blocked_at']);
                    // Берем только те, которые еще не прошли
                    if ($blockedAtTimestamp > $currentTimestamp) {
                        if ($maxBlockedAtTimestamp === null || $blockedAtTimestamp > $maxBlockedAtTimestamp) {
                            $maxBlockedAtTimestamp = $blockedAtTimestamp;
                            $maxBlockedAt = $item['blocked_at'];
                        }
                    }
                }
            }
            
            $groupData = [
                'blocked_hour' => $blockedHour,
                'items' => $items
            ];
            
            // Добавляем максимальное время окончания блокировки для группы
            if ($maxBlockedAt) {
                $groupData['blocked_at'] = $maxBlockedAt;
            }
            
            $formattedResults[] = $groupData;
        }

        Yii::info("actionWipeBlockItems: Returning " . count($formattedResults) . " groups with total " . count($drops) . " items for serverId={$server->id}", 'gamestores');

        return $this->successResponseGameStores($formattedResults);
    }

    /**
     * Получить категории товаров
     * shop.categories
     * Body: пустой (не требует авторизации)
     */
    private function actionShopCategories($bodyParams, $server)
    {
        // Получаем все категории напрямую из базы (без фильтрации по show_main_block)
        // Это гарантирует, что все категории, включая "прочее", будут возвращены
        $allCategories = Category::find()
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        
        $result = [];
        
        // Добавляем искусственную категорию "Популярное" в начало списка
        $result[] = [
            'id' => 0,
            'name' => 'Популярное',
            'tag' => 'popular',
            'image' => '',
            'sort' => -1, // Минимальный sort, чтобы была первой
        ];
        
        foreach ($allCategories as $category) {
            // Пропускаем категорию "Наборы" (tag = "sets")
            if (strtolower($category->tag ?? '') === 'sets') {
                continue;
            }
            
            $result[] = [
                'id' => $category->id,
                'name' => Yii::t('database', $category->name),
                'tag' => $category->tag ?? '',
                'image' => $category->getImageUrl() ?? '',
                'sort' => $category->sort ?? 0,
            ];
        }
        
        Yii::info("actionShopCategories: Returning " . count($result) . " categories (including Popular) for serverId={$server->id}", 'gamestores');
        
        return $this->successResponseGameStores($result);
    }

    /**
     * Получить товары
     * shop.products
     * Body: {"categoryId": 123} (опционально, если не указан - вернуть все товары)
     */
    private function actionShopProducts($bodyParams, $server)
    {
        $categoryId = $bodyParams['categoryId'] ?? $bodyParams['category_id'] ?? null;
        
        // Получаем только товары с show_main_block=false (не из главного блока)
        $drops = Drop::getForMarket(false);
        
        // Получаем изображения размером 150px
        $images = Drop::productsImages();
        
        // Получаем информацию о вайп-блоке для этого сервера
        $blockedList = DropBlocked::getBlockedList($server->id, true);
        
        $result = [];
        
        // Если выбрана категория "Популярное" (ID = 0), возвращаем первые 28 товаров
        if ($categoryId === 0 || $categoryId === '0') {
            $popularCount = 0;
            foreach ($drops as $drop) {
                // Возвращаем только товары типа TYPE_DROP (обычные предметы)
                if ($drop->drop_type != Drop::TYPE_DROP) {
                    continue;
                }
                
                // Ограничиваем количество до 28
                if ($popularCount >= 28) {
                    break;
                }
                
                // Проверяем вайп-блок
                $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
                
                // Получаем изображение
                $imageUrl = $images[$drop->id]['150px'] ?? '';
                if (empty($imageUrl) && $drop->imageOrig) {
                    $imageUrl = $drop->imageOrig->getImagePubUrl();
                }
                
                // Получаем blocked_at из drop_blocked
                $blockedAt = $blockedList[$drop->id] ?? null;
                $blockedAtUtc = null;
                if ($blockedAt) {
                    try {
                        $moscowTz = new \DateTimeZone('Europe/Moscow');
                        $blockedAtMoscow = new \DateTime($blockedAt, $moscowTz);
                        $blockedAtMoscow->setTimezone(new \DateTimeZone('UTC'));
                        $blockedAtUtc = $blockedAtMoscow->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $blockedAtUtc = $blockedAt;
                    }
                }
                
                // Получаем реальную цену с учетом скидки и плавающей цены
                $realPrice = $drop->getRealPrice(true);
                
                $item = [
                    'id' => $drop->id,
                    'name' => Yii::t('database', $drop->name),
                    'price' => (float)$realPrice,
                    'image' => $imageUrl,
                    'rust_id' => $drop->rust_id ?? 0,
                    'count' => $drop->count ?? 1,
                    'drop_type' => $drop->drop_type ?? 0,
                    'category_id' => $drop->category_id ?? 0,
                    'is_blocked' => $wipeBlockCheck['isBlocked'],
                    'left_time' => (float)$wipeBlockCheck['leftTime'],
                ];
                
                if ($blockedAtUtc) {
                    $item['blocked_at'] = $blockedAtUtc;
                }
                
                $result[] = $item;
                $popularCount++;
            }
            
            return $this->successResponseGameStores($result);
        }
        
        // Обычная обработка для остальных категорий
        foreach ($drops as $drop) {
            // Пропускаем товары типа SELECT (Товар с выбором)
            if ($drop->drop_type == Drop::TYPE_SELECT) {
                continue;
            }
            
            // Фильтруем по категории, если указана
            if ($categoryId !== null && $drop->category_id != $categoryId) {
                continue;
            }
            
            // Проверяем вайп-блок
            $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
            
            // Получаем изображение
            $imageUrl = $images[$drop->id]['150px'] ?? '';
            if (empty($imageUrl) && $drop->imageOrig) {
                $imageUrl = $drop->imageOrig->getImagePubUrl();
            }
            
            // Получаем blocked_at из drop_blocked
            $blockedAt = $blockedList[$drop->id] ?? null;
            $blockedAtUtc = null;
            if ($blockedAt) {
                try {
                    $moscowTz = new \DateTimeZone('Europe/Moscow');
                    $blockedAtMoscow = new \DateTime($blockedAt, $moscowTz);
                    $blockedAtMoscow->setTimezone(new \DateTimeZone('UTC'));
                    $blockedAtUtc = $blockedAtMoscow->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $blockedAtUtc = $blockedAt;
                }
            }
            
            // Получаем реальную цену с учетом скидки и плавающей цены
            $realPrice = $drop->getRealPrice(true);
            
            $item = [
                'id' => $drop->id,
                'name' => Yii::t('database', $drop->name),
                'price' => (float)$realPrice,
                'image' => $imageUrl,
                'rust_id' => $drop->rust_id ?? 0,
                'count' => $drop->count ?? 1,
                'drop_type' => $drop->drop_type ?? 0,
                'category_id' => $drop->category_id ?? 0,
                'is_blocked' => $wipeBlockCheck['isBlocked'],
                'left_time' => (float)$wipeBlockCheck['leftTime'],
            ];
            
            if ($blockedAtUtc) {
                $item['blocked_at'] = $blockedAtUtc;
            }
            
            $result[] = $item;
        }
        
        return $this->successResponseGameStores($result);
    }

    /**
     * Получить баланс пользователя
     * shop.balance
     * Body: {"steamId": "7656119..."}
     */
    private function actionShopBalance($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }
        
        $balance = $user->getPersonalBalance();
        
        return $this->successResponseGameStores([
            'balance' => (float)$balance->balance,
            'balanceCeil' => (int)ceil($balance->balance),
            'balanceFormat' => $balance->getBalanceFormat(),
        ]);
    }

    /**
     * Добавить товар в корзину
     * shop.addToBasket
     * Body: {"steamId": "7656119...", "dropId": 123, "quantity": 1}
     */
    private function actionShopAddToBasket($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }
        
        $dropId = $bodyParams['dropId'] ?? $bodyParams['drop_id'] ?? null;
        $quantity = isset($bodyParams['quantity']) ? (int)$bodyParams['quantity'] : 1;
        
        if (empty($dropId)) {
            return $this->errorResponseGameStores('Отсутствует параметр dropId', 105);
        }
        
        if ($quantity < 1 || $quantity > 100) {
            return $this->errorResponseGameStores('Количество должно быть от 1 до 100', 105);
        }
        
        $drops = Drop::getDropListAll();
        $drop = $drops[$dropId] ?? null;
        
        if (!$drop || $drop->status != Drop::STATUS_ACTIVE || $drop->market_status != Drop::MARKET_STATUS_ACTIVE) {
            return $this->errorResponseGameStores('Товар не найден или недоступен для покупки', 107);
        }
        
        // Проверяем вайп-блок
        $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
        if ($wipeBlockCheck['isBlocked']) {
            return $this->errorResponseGameStores('Предмет временно заблокирован вайп блоком', 109);
        }
        
        // Создаем UserDrop со статусом STATUS_ACTIVE (товар в корзине)
        $dropCount = ($drop->count ?? 1) * $quantity;
        $userDrop = UserDrop::createRecord(
            $user->id,
            $drop->id,
            null, // box_id
            null, // sets_id
            UserDrop::STATUS_ACTIVE, // Товар в корзине
            false, // auto
            $dropCount, // count
            null, // created_at
            null // parent_drop_id
        );
        
        return $this->successResponseGameStores([
            'success' => true,
            'userDropId' => $userDrop->id,
        ]);
    }

    /**
     * Купить товар (мгновенная покупка)
     * shop.buy
     * Body: {"steamId": "7656119...", "dropId": 123, "quantity": 1}
     */
    private function actionShopBuy($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }
        
        $dropId = $bodyParams['dropId'] ?? $bodyParams['drop_id'] ?? null;
        $quantity = isset($bodyParams['quantity']) ? (int)$bodyParams['quantity'] : 1;
        
        if (empty($dropId)) {
            return $this->errorResponseGameStores('Отсутствует параметр dropId', 105);
        }
        
        if ($quantity < 1 || $quantity > 100) {
            return $this->errorResponseGameStores('Количество должно быть от 1 до 100', 105);
        }
        
        $drops = Drop::getDropListAll();
        $drop = $drops[$dropId] ?? null;
        
        if (!$drop || $drop->status != Drop::STATUS_ACTIVE || $drop->market_status != Drop::MARKET_STATUS_ACTIVE) {
            return $this->errorResponseGameStores('Товар не найден или недоступен для покупки', 107);
        }
        
        // Проверяем вайп-блок
        $wipeBlockCheck = $this->checkWipeBlock($drop, $server);
        if ($wipeBlockCheck['isBlocked']) {
            return $this->errorResponseGameStores('Предмет временно заблокирован вайп блоком', 109);
        }
        
        // Рассчитываем цену используя RealPrice
        $pricePerItem = $drop->getRealPrice(true);
        $totalPrice = $pricePerItem * $quantity;
        
        // Проверяем баланс
        $balance = $user->getPersonalBalance();
        if ($totalPrice > $balance->balanceCeil) {
            return $this->errorResponseGameStores('Недостаточно средств на счете', 108);
        }
        
        // Начинаем транзакцию
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем Invoice для списания средств
            $comment = Yii::t('common', 'Мгновенная покупка предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ]);
            
            Invoice::createRecord(
                $user->id,
                $totalPrice,
                Invoice::TYPE_PAYMENT_MARKET_DROP,
                null, // box_id
                null, // sets_id
                $drop->id, // drop_id
                $comment
            );
            
            // Создаем UserDrop со статусом STATUS_ACTIVE (товар в корзине, еще не выдан)
            // Каждая единица товара создает отдельную запись в user_drop
            $userDropIds = [];
            
            // Создаем отдельную запись для каждой единицы товара
            for ($i = 0; $i < $quantity; $i++) {
                if ($drop->drop_type == 2) {
                    // TYPE_SET - создаем записи для всех subDrops
                    $subDrops = DropDrop::find()
                        ->where(['parent_drop_id' => $drop->id])
                        ->with('drop')
                        ->all();
                    
                    foreach ($subDrops as $subDropRelation) {
                        if ($subDropRelation->drop) {
                            $subDropCount = $subDropRelation->count ?? 1;
                            $userDrop = UserDrop::createRecord(
                                $user->id,
                                $subDropRelation->drop_id,
                                null, // box_id
                                null, // sets_id
                                UserDrop::STATUS_ACTIVE, // Товар в корзине (статус 1)
                                false, // auto
                                $subDropCount, // count (без умножения на quantity)
                                null, // created_at
                                $drop->id // parent_drop_id
                            );
                            $userDropIds[] = $userDrop->id;
                        }
                    }
                } else {
                    // Обычный товар - создаем отдельную запись для каждой единицы
                    $dropCount = $drop->count ?? 1;
                    $userDrop = UserDrop::createRecord(
                        $user->id,
                        $drop->id,
                        null, // box_id
                        null, // sets_id
                        UserDrop::STATUS_ACTIVE, // Товар в корзине (статус 1)
                        false, // auto
                        $dropCount, // count (без умножения на quantity)
                        null, // created_at
                        null // parent_drop_id
                    );
                    $userDropIds[] = $userDrop->id;
                }
            }
            
            // Пересчитываем баланс
            $balance->recalculateBalance();
            $newBalance = $balance->balanceCeil;
            
            // Коммитим транзакцию
            $dbTransaction->commit();
            
            return $this->successResponseGameStores([
                'success' => true,
                'newBalance' => (string)$newBalance,
                'balanceCeil' => (int)$newBalance,
                'balanceFormat' => $balance->getBalanceFormat(),
                'userDropIds' => $userDropIds,
            ]);
            
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            Yii::error("Error in actionShopBuy: " . $e->getMessage(), 'gamestores');
            return $this->errorResponseGameStores('Ошибка при покупке товара: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Удалить товар из корзины
     * shop.removeFromBasket
     * Body: {"steamId": "7656119...", "basketId": 123}
     */
    private function actionShopRemoveFromBasket($bodyParams, $server)
    {
        // Авторизация по steam_id
        try {
            $user = $this->getUserBySteamId($bodyParams, $server);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponseGameStores($e->getMessage(), 105);
        }
        
        $basketId = $bodyParams['basketId'] ?? null;
        
        if (empty($basketId)) {
            return $this->errorResponseGameStores('Отсутствует параметр basketId', 105);
        }
        
        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);
        
        if (!$userDrop) {
            return $this->errorResponseGameStores('Товар не найден в корзине', 107);
        }
        
        // Проверка принадлежности
        if ($user->id != $userDrop->user_id) {
            return $this->errorResponseGameStores('Товар вам не принадлежит!', 107);
        }
        
        // Проверяем, что товар в корзине (STATUS_ACTIVE или STATUS_WAIT)
        if ($userDrop->status != UserDrop::STATUS_ACTIVE && $userDrop->status != UserDrop::STATUS_WAIT) {
            return $this->errorResponseGameStores('Товар уже получен или удален', 107);
        }
        
        // Удаляем товар из корзины
        $userDrop->delete();
        
        return $this->successResponseGameStores([
            'success' => true,
            'message' => 'Товар удален из корзины'
        ]);
    }

    /**
     * Получить информацию для MenuBase (вайпы, команды, правила, FAQ, помощь, описание)
     * menubase.info
     * Body: пустой (универсальный метод, вызывается один раз при инициализации плагина)
     */
    private function actionMenuBaseInfo($bodyParams, $server)
    {
        $result = [];
        
        // Информация о вайпах
        $moscowTz = new \DateTimeZone('Europe/Moscow');
        
        // Получаем дату последнего вайпа
        $lastWipeFormatted = null;
        if (!empty($server->wipe)) {
            try {
                $lastWipeDate = new \DateTime($server->wipe);
                $lastWipeDate->setTimezone($moscowTz);
                $lastWipeFormatted = $this->formatWipeDate($lastWipeDate);
            } catch (\Exception $e) {
                // Игнорируем ошибку
            }
        }
        
        // Получаем дату следующего вайпа
        $nextWipeFormatted = null;
        if (!empty($server->next_wipe)) {
            try {
                $nextWipeDate = new \DateTime($server->next_wipe);
                $nextWipeDate->setTimezone($moscowTz);
                $nextWipeFormatted = $this->formatWipeDate($nextWipeDate);
            } catch (\Exception $e) {
                // Игнорируем ошибку
            }
        }
        
        $result['lastWipe'] = $lastWipeFormatted;
        $result['nextWipe'] = $nextWipeFormatted;
        
        // Команды сервера
        $result['commands'] = $this->getMenuBaseCommands($server);
        
        // Правила сервера
        $result['rules'] = $this->getMenuBaseRules($server);
        
        // FAQ (Часто задаваемые вопросы)
        $result['faq'] = $this->getMenuBaseFAQ($server);
        
        // Помощь (Help sections)
        $result['help'] = $this->getMenuBaseHelp($server);
        
        // Описание сервера (теги)
        $result['description'] = $this->getMenuBaseDescription($server);
        
        return $this->successResponseGameStores($result);
    }
    
    /**
     * Получить команды для MenuBase в нужном формате
     * Всегда возвращает полную структуру с fallback значениями
     */
    private function getMenuBaseCommands($server)
    {
        $commands = [];
        
        // Ищем категорию "Команды на сервере"
        $commandsCategory = \common\models\servers\ServersRulesCategory::find()
            ->where(['name' => 'Команды на сервере'])
            ->one();
        
        if ($commandsCategory) {
            // Получаем все правила для сервера
            $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);
            
            // Группируем команды по категориям
            $commandsByCategory = [];
            
            foreach ($rules as $rule) {
                if ($rule->category_id == $commandsCategory->id) {
                    $categoryName = $rule->title ?? 'ОСНОВНЫЕ';
                    
                    if (!isset($commandsByCategory[$categoryName])) {
                        $commandsByCategory[$categoryName] = [];
                    }
                    
                    // Извлекаем команды из content
                    $content = strip_tags($rule->content);
                    $content = trim($content);
                    
                    if (!empty($content)) {
                        // Разбиваем на строки
                        $lines = explode("\n", $content);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                // Парсим команду: "описание: команда" или просто "команда"
                                $parts = explode(':', $line, 2);
                                if (count($parts) == 2) {
                                    $commandsByCategory[$categoryName][] = [
                                        'Description' => trim($parts[0]),
                                        'Text' => trim($parts[1])
                                    ];
                                } else {
                                    $commandsByCategory[$categoryName][] = [
                                        'Description' => $line,
                                        'Text' => $line
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            
            // Преобразуем в нужный формат
            foreach ($commandsByCategory as $categoryName => $cmds) {
                $commands[$categoryName] = $cmds;
            }
        }
        
        // Если нет команд из базы, возвращаем значения по умолчанию из изначального плагина
        if (empty($commands)) {
            $commands = [
                'ОСНОВНЫЕ' => [
                    [
                        'Description' => 'Открыть раздел Menu',
                        'Text' => 'bind <key> menu'
                    ],
                    [
                        'Description' => 'Открыть раздел Menu #2',
                        'Text' => 'bind <key> menu test'
                    ]
                ],
                'ДРУЗЬЯ' => [
                    [
                        'Description' => 'Открыть раздел друзей',
                        'Text' => 'bind <key> menu'
                    ],
                    [
                        'Description' => 'Открыть раздел друзей #2',
                        'Text' => 'bind <key> menu test'
                    ]
                ]
            ];
        }
        
        return $commands;
    }
    
    /**
     * Получить правила для MenuBase
     * Всегда возвращает полную структуру с fallback значениями
     */
    private function getMenuBaseRules($server)
    {
        $rulesStrings = [];
        
        // Получаем все правила для сервера
        $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);
        
        // Ищем категорию "Команды на сервере", чтобы исключить её
        $commandsCategory = \common\models\servers\ServersRulesCategory::find()
            ->where(['name' => 'Команды на сервере'])
            ->one();
        
        $commandsCategoryId = $commandsCategory ? $commandsCategory->id : null;
        
        foreach ($rules as $rule) {
            // Пропускаем команды
            if ($rule->category_id == $commandsCategoryId) {
                continue;
            }
            
            // Получаем текст правила
            $content = strip_tags($rule->content);
            $content = trim($content);
            
            if (!empty($content)) {
                $rulesStrings[] = $content;
            }
        }
        
        // Если нет правил из базы, возвращаем значения по умолчанию из изначального плагина
        if (empty($rulesStrings)) {
            $rulesStrings = [
                '124124124',
                '124124124',
                '12354213525',
                '12451235235',
                '5235235'
            ];
        }
        
        return $rulesStrings;
    }
    
    /**
     * Получить FAQ для MenuBase
     * Всегда возвращает полную структуру с fallback значениями
     */
    private function getMenuBaseFAQ($server)
    {
        $faqSections = [];
        
        // Ищем категорию "FAQ" или "Часто задаваемые вопросы"
        $faqCategory = \common\models\servers\ServersRulesCategory::find()
            ->where(['or', ['name' => 'FAQ'], ['name' => 'Часто задаваемые вопросы']])
            ->one();
        
        if ($faqCategory) {
            $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);
            
            foreach ($rules as $rule) {
                if ($rule->category_id == $faqCategory->id) {
                    $key = 'faq_' . $rule->id;
                    $label = $rule->title ?? strip_tags($rule->content);
                    $content = strip_tags($rule->content);
                    
                    // Вычисляем примерный offset на основе длины текста
                    $textLength = mb_strlen(strip_tags($content));
                    $panelDownOffset = max(-50, -($textLength / 10) * 2);
                    
                    $faqSections[$key] = [
                        'Label' => $label,
                        'InsideText' => $content,
                        'PanelDownOffset' => $panelDownOffset
                    ];
                }
            }
        }
        
        // Если нет FAQ из базы, возвращаем значения по умолчанию из изначального плагина
        if (empty($faqSections)) {
            $faqSections = [
                'promocodes' => [
                    'Label' => 'ГДЕ МОЖНО НАЙТИ ПРОМОКОДЫ?',
                    'InsideText' => 'Промокоды можно найти в наших социальных сетях. Там мы публикуем новости, акции и промокоды. Также, в закрытых чатах Discord, каждый вайп, мы выкладываем промокоды для бустеров сервера.',
                    'PanelDownOffset' => -50
                ],
                'shitpost' => [
                    'Label' => 'Ну пиздец он долгий, когда инфо?',
                    'InsideText' => 'Да почти готово, ща правила доделаю и четенько',
                    'PanelDownOffset' => -50
                ]
            ];
        }
        
        return $faqSections;
    }
    
    /**
     * Получить Help секции для MenuBase
     * Всегда возвращает полную структуру с fallback значениями
     */
    private function getMenuBaseHelp($server)
    {
        $helpSections = [];
        
        // Ищем категорию "Помощь" или "Help"
        $helpCategory = \common\models\servers\ServersRulesCategory::find()
            ->where(['or', ['name' => 'Помощь'], ['name' => 'Help']])
            ->one();
        
        if ($helpCategory) {
            $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);
            
            // Группируем по title (основные секции)
            // Если у правила есть title, используем его как ключ секции
            // Если нет title, создаем отдельную секцию для каждого правила
            $sectionsByTitle = [];
            
            foreach ($rules as $rule) {
                if ($rule->category_id == $helpCategory->id) {
                    // Если есть title, используем его как ключ секции
                    // Если нет title, используем content как ключ
                    $sectionKey = null;
                    $title = $rule->title ?? null;
                    
                    if ($title) {
                        // Используем title как ключ секции
                        $sectionKey = 'help_' . md5($title);
                        
                        if (!isset($sectionsByTitle[$sectionKey])) {
                            $sectionsByTitle[$sectionKey] = [
                                'TextOnButton' => $title,
                                'DrawOrder' => $rule->sort ?? 0,
                                'SubSections' => []
                            ];
                        }
                        
                        // Добавляем подраздел с content
                        $content = $rule->content;
                        $textLength = mb_strlen(strip_tags($content));
                        $downOffset = max(50, ($textLength / 10) * 2);
                        
                        $sectionsByTitle[$sectionKey]['SubSections'][] = [
                            'Label' => $title,
                            'InternalText' => $content, // Сохраняем HTML для Help
                            'DownOffset' => $downOffset
                        ];
                    } else {
                        // Если нет title, создаем отдельную секцию для каждого правила
                        $sectionKey = 'help_' . $rule->id;
                        $content = $rule->content;
                        $textLength = mb_strlen(strip_tags($content));
                        $downOffset = max(50, ($textLength / 10) * 2);
                        
                        // Используем первые слова content как название секции
                        $label = mb_substr(strip_tags($content), 0, 30);
                        if (mb_strlen($label) >= 30) {
                            $label .= '...';
                        }
                        
                        $sectionsByTitle[$sectionKey] = [
                            'TextOnButton' => $label ?: 'Помощь',
                            'DrawOrder' => $rule->sort ?? 0,
                            'SubSections' => [
                                [
                                    'Label' => $label ?: 'Информация',
                                    'InternalText' => $content,
                                    'DownOffset' => $downOffset
                                ]
                            ]
                        ];
                    }
                }
            }
            
            $helpSections = $sectionsByTitle;
        }
        
        // Если нет Help секций из базы, возвращаем значения по умолчанию из изначального плагина
        if (empty($helpSections)) {
            $helpSections = [
                'menu' => [
                    'TextOnButton' => 'МЕНЮ',
                    'DrawOrder' => 0,
                    'SubSections' => [
                        [
                            'Label' => 'ЧТО ЭТО ТАКОЕ?',
                            'InternalText' => 'Ивент "Спутник" - это главный ивент сервера, в котором можно получить ценный лут и Volt\'s молнии',
                            'DownOffset' => 50
                        ],
                        [
                            'Label' => 'ОПИСАНИЕ ИВЕНТА',
                            'InternalText' => 'Два раза в день (12:00 и 18:00) автоматически запускается событие. Во время его начала на экране появляется уведомление о падении обломков "Спутника". Через некоторое время в чате появляется информация о квадратах, в которых упали обломки. Также, на G-MAP отображаются точки, выделенные красным кругом.',
                            'DownOffset' => 0
                        ]
                    ]
                ],
                'friends' => [
                    'TextOnButton' => 'ДРУЗЬЯ',
                    'DrawOrder' => 1,
                    'SubSections' => [
                        [
                            'Label' => 'Чё то про друзейВ',
                            'InternalText' => 'эх шарик я как и ты жил на цепи\nрубал хозяйские харчи',
                            'DownOffset' => 70
                        ],
                        [
                            'Label' => '<color=red>оу</color>',
                            'InternalText' => 'Какие пиздатые оффсеты для тонкой настройки текста ммммм\n<size=5>руки бы разработчику поотрывать нахуй за эту дрочь</size>',
                            'DownOffset' => 0
                        ]
                    ]
                ]
            ];
        }
        
        return $helpSections;
    }
    
    /**
     * Получить описание сервера (теги)
     */
    private function getMenuBaseDescription($server)
    {
        $description = [];
        
        // Загружаем теги сервера с помощью связи
        $tags = \common\models\servers\ServersTags::find()
            ->innerJoin('servers_tags_relation', 'servers_tags_relation.tag_id = servers_tags.id')
            ->where(['servers_tags_relation.server_id' => $server->id])
            ->orderBy(['servers_tags.sort' => SORT_ASC])
            ->all();
        
        foreach ($tags as $tag) {
            if (!empty($tag->description)) {
                $description[] = [
                    'name' => $tag->name ?? '',
                    'short_description' => $tag->short_description ?? '',
                    'description' => $tag->description ?? ''
                ];
            }
        }
        
        return $description;
    }
    
    /**
     * Получить список баннеров для MenuBase
     * Возвращает массив баннеров с URL изображений
     * Формат: [{"id": 0, "image": "https://example.com/banner1.png"}, {"id": 1, "image": "https://example.com/banner2.png"}]
     */
    private function actionMenuBaseBanners($bodyParams, $server)
    {
        $result = [];
        
        // TODO: Получить баннеры из базы данных для сервера
        // Пока возвращаем пустой массив - баннеры будут браться из конфига плагина как fallback
        // Пример структуры:
        // $result[] = [
        //     'id' => 0,
        //     'image' => 'https://yourdomain.com/images/banner1.png'
        // ];
        
        return $this->successResponseGameStores($result);
    }
    
    /**
     * Форматировать дату вайпа в формат "5 февраля 16:00 МСК"
     */
    private function formatWipeDate(\DateTime $date)
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        
        $day = (int)$date->format('d');
        $month = (int)$date->format('m');
        $time = $date->format('H:i');
        
        $monthName = $months[$month] ?? '';
        
        return "{$day} {$monthName} {$time} МСК";
    }
}


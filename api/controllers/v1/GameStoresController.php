<?php

namespace api\controllers\v1;

use common\components\payments\PaymentApi;
use common\components\queue\process\ActivatedDropJob;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\invoice\Deposit;
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
     * @return User
     * @throws UnauthorizedHttpException
     */
    protected function getUserBySteamId($bodyParams = [])
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
            return $this->errorResponse('Сервер с таким IP и PORT не найден', 103);
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
            
            default:
                return $this->errorResponse('Метод не найден!', 105);
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
            return $this->errorResponse('Сервер с таким IP и PORT не найден', 103);
        }

        // Получаем параметры запроса
        $steamId = $bodyParams['steam_id'] ?? Yii::$app->request->post('steam_id');
        $amount = $bodyParams['amount'] ?? Yii::$app->request->post('amount');
        $methodName = $bodyParams['method_name'] ?? Yii::$app->request->post('method_name', 'Custom');
        $createPlayer = $bodyParams['create_player'] ?? Yii::$app->request->post('create_player', 'false');

        // Валидация параметров
        if (empty($steamId)) {
            return $this->errorResponse('Отсутствует обязательный параметр: steam_id', 400);
        }

        if (empty($amount)) {
            return $this->errorResponse('Отсутствует обязательный параметр: amount', 400);
        }

        $steamId = (string)$steamId;
        $amount = (int)$amount;

        // Проверка steam_id
        if (strlen($steamId) !== 17 || !is_numeric($steamId)) {
            return $this->errorResponse('Неверный формат steam_id', 400);
        }

        // Проверка суммы
        if ($amount < 1 || $amount > 1000000) {
            return $this->errorResponse('Сумма должна быть в диапазоне от 1 до 1000000', 400);
        }

        // Пытаемся получить пользователя по steam_id (авторизация)
        // Если пользователь не найден, создаем его (для платежей это допустимо)
        try {
            $user = $this->getUserBySteamId($bodyParams);
        } catch (UnauthorizedHttpException $e) {
            // Если пользователь не найден, создаем его
            $user = User::findBySteamId($steamId, true, 'gamestores_payment');
            
            if (!$user) {
                return $this->errorResponse('Не удалось найти или создать пользователя', 400);
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
                return $this->errorResponse('Не удалось создать платеж', 500);
            }

            // Получаем API провайдера для Tinkoff и создаем платеж
            $paymentApi = PaymentApi::getInstance($paymentType);
            $response = $paymentApi->create($deposit);
            
            if (empty($response)) {
                $deposit->status = Deposit::STATUS_CANCELED;
                $deposit->save(false);
                return $this->errorResponse('Не удалось создать платеж в системе оплаты', 500);
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
            
            return $this->errorResponse('Ошибка при создании платежа: ' . $e->getMessage(), 500);
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
            $user = $this->getUserBySteamId($bodyParams);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), 105);
        }
        
        $basketId = $bodyParams['basketId'] ?? null;
        
        if (empty($basketId)) {
            return $this->errorResponse('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);
        
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return $this->errorResponse('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности предмета
        if ($user->steam_id != $userDrop->user->steam_id) {
            return $this->errorResponse('Товар вам не принадлежит!', 107);
        }

        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;
        
        if (!$drop) {
            return $this->errorResponse('Предмет не найден', 107);
        }

        // Проверка, что rust_id не равен 0 (для предметов)
        if (empty($drop->command) && (empty($drop->rust_id) || $drop->rust_id == 0)) {
            Yii::error("GameStores: Drop {$drop->id} has invalid rust_id: {$drop->rust_id}", 'gamestores');
            return $this->errorResponse('Предмет имеет неверный rust_id', 107);
        }

        // Получаем картинки для определения URL (если rust_id нет)
        $images = Drop::productsImages();
        $item = $this->formatItem($userDrop, $drop, $images, true);
        
        // Логирование для отладки
        Yii::info("GameStores baskets.item response for basketId {$basketId}: " . json_encode($item, JSON_UNESCAPED_UNICODE), 'gamestores');
        
        return $this->successResponse($item);
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
            $user = $this->getUserBySteamId($bodyParams);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), 105);
        }

        /** @var UserDrop[] $userDrops */
        $userDrops = $user->getUserDrop()
            ->andWhere(['IN', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_WAIT]])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $data = [];
        $images = Drop::productsImages();
        $drops = Drop::getDropListAll();
        $itemsBlocked = DropBlocked::getBlockedList($server->id);
        
        foreach ($userDrops as $userDrop) {
            $drop = $drops[$userDrop->drop_id] ?? null;
            if (!$drop) continue;
            
            $item = $this->formatBasketItem($userDrop, $drop, $images, $itemsBlocked);
            $data[] = $item;
        }

        return $this->successResponse($data);
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
            $user = $this->getUserBySteamId($bodyParams);
        } catch (UnauthorizedHttpException $e) {
            return $this->errorResponse($e->getMessage(), 105);
        }
        
        $basketId = $bodyParams['basketId'] ?? null;
        
        if (empty($basketId)) {
            return $this->errorResponse('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);
        
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return $this->errorResponse('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности
        if ($user->steam_id != $userDrop->user->steam_id) {
            return $this->errorResponse('Товар вам не принадлежит!', 107);
        }

        $userDrop->sended_at = date('Y-m-d H:i:s');
        $userDrop->status = UserDrop::STATUS_SENDED;

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

        return $this->successResponse(null);
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
        return $this->successResponse([]);
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
     * Форматировать предмет для baskets.item
     */
    private function formatItem($userDrop, $drop, $images = [], $includeSubDrop = false)
    {
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
            $item['img'] = $images[$userDrop->drop_id]['64px'] ?? '';
        } else {
            // Предмет
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id ?? 0;
            
            // Для предметов плагин ожидает data["data"]["itemId"]
            // Всегда передаем itemId (даже если 0), чтобы плагин мог обработать
            $rustId = $drop->rust_id ?? 0;
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
                $item['img'] = $images[$userDrop->drop_id]['64px'] ?? '';
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
    private function formatBasketItem($userDrop, $drop, $images, $itemsBlocked)
    {
        // Определяем картинку: если есть rust_id, используем его, иначе картинку с сайта
        $img = '';
        if (!empty($drop->command)) {
            // Команда - нет rust_id, используем картинку с сайта
            $img = $images[$userDrop->drop_id]['64px'] ?? '';
        } else {
            // Предмет: если есть rust_id, используем его как идентификатор
            if (!empty($drop->rust_id)) {
                $img = (string)$drop->rust_id;
            } else {
                // Если rust_id нет, используем картинку с сайта
                $img = $images[$userDrop->drop_id]['64px'] ?? '';
            }
        }
        
        // Убеждаемся, что img всегда строка (не null)
        if ($img === null) {
            $img = '';
        }
        
        // Убеждаемся, что amount всегда число (не null)
        $amount = $userDrop->count ?? 0;
        
        $item = [
            'id' => $userDrop->id,
            'basketId' => $userDrop->id, // Для совместимости
            'productId' => (string)$drop->id, // ID продукта (drop)
            'amount' => $amount,
            'name' => $drop->name,
            'img' => $img,
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
    private function successResponse($data)
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
    private function errorResponse($message, $code)
    {
        return [
            'result' => 'fail',
            'message' => $message,
            'code' => $code,
        ];
    }
}


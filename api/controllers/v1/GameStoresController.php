<?php

namespace api\controllers\v1;

use common\components\queue\process\ActivatedDropJob;
use common\models\box\Drop;
use common\models\box\DropBlocked;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserDrop;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер для работы с GameStoresRUST плагином
 * Формат API: POST запросы с методами как путями
 * URL: /v1/{method}?store_id=XXX&server_id=YYY
 * Body: JSON с параметрами
 * Headers: storeId, secretKey, serverId (опционально, для дополнительной проверки)
 */
class GameStoresController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Основной метод для обработки всех запросов GameStoresRUST
     * 
     * @param string $method Метод API (baskets.item, baskets.bySteamId, etc.)
     * @return Response
     */
    public function actionIndex($method)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем параметры из query string
        $storeId = Yii::$app->request->get('store_id');
        $serverId = Yii::$app->request->get('server_id');
        $queryServerIp = Yii::$app->request->get('server_ip');
        $queryServerPort = Yii::$app->request->get('server_port');

        // Получаем параметры из headers (если есть)
        $headerStoreId = Yii::$app->request->headers->get('storeId');
        $headerServerId = Yii::$app->request->headers->get('serverId');
        $headerServerIp = Yii::$app->request->headers->get('serverIp');
        $headerServerPort = Yii::$app->request->headers->get('serverPort');

        // Получаем body параметры (POST)
        // GameStoresRUST отправляет данные как form-data через UnityWebRequest.Post
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

        // Находим сервер по IP и PORT из headers или query string
        // Приоритет: headers > query string
        $serverIp = $headerServerIp ?: $queryServerIp;
        $serverPort = $headerServerPort ?: $queryServerPort;
        
        $server = $this->findServer($storeId, $serverId, $serverIp, $serverPort, $headerServerId);
        
        if (!$server) {
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
     * Получить информацию о предмете в корзине
     * baskets.item
     * Body: {"basketId": 123}
     */
    private function actionBasketsItem($bodyParams, $server)
    {
        $basketId = $bodyParams['basketId'] ?? null;
        
        if (empty($basketId)) {
            return $this->errorResponse('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);
        
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return $this->errorResponse('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности предмета (если передан steamId в body)
        $steamId = $bodyParams['steamId'] ?? null;
        if (!empty($steamId) && $steamId != $userDrop->user->steam_id) {
            return $this->errorResponse('Товар вам не принадлежит!', 107);
        }

        $drops = Drop::getDropListAll();
        $drop = $drops[$userDrop->drop_id] ?? null;
        
        if (!$drop) {
            return $this->errorResponse('Предмет не найден', 107);
        }

        $item = $this->formatItem($userDrop, $drop, true);
        
        return $this->successResponse($item);
    }

    /**
     * Получить корзину игрока по Steam ID
     * baskets.bySteamId
     * Body: {"steamId": "7656119..."}
     */
    private function actionBasketsBySteamId($bodyParams, $server)
    {
        $steamId = $bodyParams['steamId'] ?? null;
        
        if (empty($steamId)) {
            return $this->errorResponse('Отсутствует параметр steamId', 105);
        }

        /** @var User $user */
        $user = User::find()
            ->andWhere(['steam_id' => $steamId])
            ->one();

        if (empty($user)) {
            return $this->errorResponse('Игрок не зарегистрирован', 105);
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
        $basketId = $bodyParams['basketId'] ?? null;
        $steamId = $bodyParams['steamId'] ?? null;
        
        if (empty($basketId)) {
            return $this->errorResponse('Отсутствует параметр basketId', 105);
        }

        /** @var UserDrop $userDrop */
        $userDrop = UserDrop::findOne($basketId);
        
        if (empty($userDrop) || ($userDrop->status !== UserDrop::STATUS_WAIT && $userDrop->status !== UserDrop::STATUS_ACTIVE)) {
            return $this->errorResponse('Предмет уже получен/продан', 107);
        }

        // Проверка принадлежности
        if (!empty($steamId) && $steamId != $userDrop->user->steam_id) {
            return $this->errorResponse('Товар вам не принадлежит!', 107);
        }

        $userDrop->sended_at = date('Y-m-d H:i:s');
        $userDrop->status = UserDrop::STATUS_SENDED;

        // Обработка статистики
        if (!empty($server) && !empty($userDrop->drop[0]->dropStat)) {
            $steamId = $userDrop->user->steam_id;
            $statistics = Statistics::find()
                ->andWhere(['steam_id' => $steamId])
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
                    $model->steam_id = $steamId;
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
        $data = [
            'link' => Yii::$app->settings->get('site_domain'),
            'default_balance' => 50,
            'servers' => [$server->id], // Список ID серверов для проверки
        ];

        return $this->successResponse($data);
    }

    /**
     * Найти сервер по IP и PORT
     * Идентификация происходит по IP и PORT сервера
     */
    private function findServer($storeId, $serverId, $serverIp = null, $serverPort = null, $headerServerId = null)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(60)
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        /** @var Servers $server */
        $server = null;
        
        // Приоритет 1: IP и PORT из headers
        if ($serverIp && $serverPort) {
            foreach ($servers as $_server) {
                // Сравниваем IP (может быть в разных форматах: с портом или без)
                $serverIpClean = $this->cleanIpAddress($_server->ip);
                $requestIpClean = $this->cleanIpAddress($serverIp);
                
                if ($serverIpClean == $requestIpClean && $_server->port == (int)$serverPort) {
                    // Если передан serverId в headers, проверяем соответствие
                    if ($headerServerId && $_server->id != $headerServerId) {
                        continue;
                    }
                    // Если передан serverId в query, проверяем соответствие
                    if ($serverId && $_server->id != $serverId) {
                        continue;
                    }
                    $server = $_server;
                    break;
                }
            }
        }
        
        // Приоритет 2: storeId и serverId из query string (для обратной совместимости)
        if (!$server && $storeId && $serverId) {
            foreach ($servers as $_server) {
                // storeId может быть ID магазина
                // serverId должен совпадать с ID сервера
                if ($_server->id == $storeId && $_server->id == $serverId) {
                    $server = $_server;
                    break;
                }
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
    private function formatItem($userDrop, $drop, $includeSubDrop = false)
    {
        $item = [
            'id' => $userDrop->id,
            'basketId' => $userDrop->id, // Для совместимости
            'amount' => $userDrop->count,
            'name' => $drop->name,
            'lvl_inspection' => 0,
            'full_only' => $drop->full_only,
            'is_blocked_building' => $drop->is_blocked_building,
            'subDrop' => [],
        ];

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

        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id;
        }

        return $item;
    }

    /**
     * Форматировать предмет для baskets.bySteamId
     */
    private function formatBasketItem($userDrop, $drop, $images, $itemsBlocked)
    {
        $item = [
            'id' => $userDrop->id,
            'basketId' => $userDrop->id, // Для совместимости
            'amount' => $userDrop->count,
            'name' => $drop->name,
            'img' => $images[$userDrop->drop_id]['64px'] ?? '',
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

        if (!empty($drop->command)) {
            $item['command'] = str_replace("\r", '', $drop->command);
            $item['type'] = "command";
            $item['item_id'] = 0;
        } else {
            $item['type'] = "item";
            $item['item_id'] = $drop->rust_id;
        }

        return $item;
    }

    /**
     * Успешный ответ
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
     * Ответ с ошибкой
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


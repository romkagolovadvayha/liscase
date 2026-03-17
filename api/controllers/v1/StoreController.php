<?php

namespace api\controllers\v1;

use Yii;
use yii\web\BadRequestHttpException;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\box\Category;
use common\models\box\DropBlocked;
use common\models\box\Drop;
use common\models\rcon\RconTasks;
use common\models\statistics\Statistics;
use common\components\queue\process\ReturnDropJob;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с магазином
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Store")
 */
class StoreController extends BaseApiController
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

        return $behaviors;
    }

    /**
     * Доступ к выдаче предметов на текущий сервер (логика как в frontend/views/store/index.php, строки 16–32).
     * — При включённом is_store на сервере: доступ всем.
     * — При выключенном is_store и выключенном hidden_store: нет доступа.
     * — При выключенном is_store и включённом hidden_store: доступ при 10 ч на сервере.
     * — При включённом user->store (признак доступа): доступ в любом случае.
     */
    private function getStoreVisible($user)
    {
        $storeVisible = false;
        if (empty($user->server)) {
            return $storeVisible;
        }
        $server = $user->server;

        if (!empty($server->is_store)) {
            $storeVisible = true;
        } elseif (empty($server->hidden_store)) {
            $storeVisible = false;
        } else {
            $playtimeMinutes = (int) Statistics::find()
                ->andWhere(['steam_id' => $user->steam_id, 'server_tag' => $server->tag, 'key' => 'playtime'])
                ->sum('value');
            $storeVisible = $playtimeMinutes >= 600;
        }
        if (!empty($user->store)) {
            $storeVisible = true;
        }
        return $storeVisible;
    }

    /**
     * Список предметов в корзине пользователя (только активные)
     *
     * @OA\Get(
     *     path="/v1/store/items",
     *     operationId="storeItems",
     *     tags={"Store"},
     *     summary="Список предметов в корзине",
     *     description="Требует JWT авторизации. Возвращает активные предметы пользователя с информацией о дропе и блокировке по вайпу.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Список предметов"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionItems()
    {
        $user = $this->getCurrentUser();
        $serverId = !empty($user->server) ? (int) $user->server->id : null;

        $storeVisible = $this->getStoreVisible($user);

        $userDrops = $user->getUserDrop()
            ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($userDrops as $userDrop) {
            $drop = Yii::$app->drop->getActiveDropById($userDrop->drop_id);
            if (!$drop) {
                continue;
            }
            $blockedAt = $serverId ? DropBlocked::getBlocked($drop->id, $serverId) : null;
            $blocked = !empty($blockedAt);
            $items[] = [
                'id' => $userDrop->id,
                'drop_id' => $drop->id,
                'count' => (int) $userDrop->count,
                'category_id' => (int) $drop->category_id,
                'name' => Yii::t('database', $drop->name),
                'image' => $drop->image100(),
                'blocked' => $blocked,
                'blocked_at' => $blocked ? $blockedAt : null,
                'can_return' => empty($userDrop->box_id) && empty($userDrop->sets_id) && empty($userDrop->parent_drop_id),
            ];
        }

        return $this->successResponse([
            'items' => $items,
            'server' => $user->server ? [
                'id' => $user->server->id,
                'name' => Yii::t('database', $user->server->name ?: $user->server->monitoring_name ?: ''),
                'tag' => $user->server->tag,
            ] : null,
            'can_deliver' => $storeVisible,
        ]);
    }

    /**
     * Список категорий магазина
     *
     * @OA\Get(
     *     path="/v1/store/categories",
     *     operationId="storeCategories",
     *     tags={"Store"},
     *     summary="Список категорий",
     *     description="Требует JWT авторизации.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Список категорий"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionCategories()
    {
        $this->getCurrentUser(); // проверка авторизации

        $cacheKey = 'api_store_categories_' . Yii::$app->language;
        $cache = Yii::$app->cache;
        $list = $cache->get($cacheKey);

        if ($list === false) {
            $categories = Category::find()
                ->orderBy(['sort' => SORT_ASC])
                ->asArray()
                ->all();

            $list = [];
            foreach ($categories as $c) {
                $list[] = [
                    'id' => (int) $c['id'],
                    'name' => Yii::t('database', $c['name']),
                    'tag' => $c['tag'] ?? '',
                    'sort' => (int) ($c['sort'] ?? 0),
                ];
            }
            $cache->set($cacheKey, $list, 3600); // 1 час
        }

        return $this->successResponse(['categories' => $list]);
    }

    /**
     * Выдача предмета на сервер
     * 
     * @OA\Post(
     *     path="/v1/store/deliver",
     *     operationId="deliverItem",
     *     tags={"Store"},
     *     summary="Выдать предмет на сервер",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"itemId", "serverId"},
     *                 @OA\Property(property="itemId", type="integer", example=123, description="ID предмета (UserDrop ID)"),
     *                 @OA\Property(property="serverId", type="integer", example=1, description="ID сервера")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Предмет выдан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Предмет или сервер не найден")
     * )
     */
    public function actionDeliver()
    {
        $user = $this->getCurrentUser();
        $post = Yii::$app->request->post();

        $itemId = $post['itemId'] ?? null;
        $serverId = $post['serverId'] ?? null;

        if (empty($itemId)) {
            return $this->errorResponse('INVALID_DATA', 'Не указан ID предмета', [], 400);
        }

        if (empty($serverId)) {
            return $this->errorResponse('INVALID_DATA', 'Не указан ID сервера', [], 400);
        }

        $userDrop = UserDrop::findOne($itemId);
        if (!$userDrop || $userDrop->user_id !== $user->id) {
            return $this->errorResponse('ITEM_NOT_FOUND', 'Предмет не найден', [], 404);
        }

        if ($userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return $this->errorResponse('INVALID_STATUS', 'Предмет не доступен для выдачи', [], 400);
        }

        // Как в ChatServer commandGetDrop: выдача только если пользователь «на сервере» (у него выбран сервер)
        if (empty($user->server)) {
            return $this->errorResponse(
                'NOT_ON_SERVER',
                Yii::t('common', 'Мы не нашли вас на сервере! Зайдите на сервер и получайте предметы через лаунчер.'),
                [],
                400
            );
        }

        if ((int) $user->server->id !== (int) $serverId) {
            return $this->errorResponse(
                'WRONG_SERVER',
                Yii::t('common', 'Выдача возможна только на тот сервер, на котором вы находитесь.'),
                [],
                400
            );
        }

        $server = $user->server;

        // Доступ к выдаче: как в frontend/views/store/index.php (магазин/донат/10 ч)
        if (!$this->getStoreVisible($user)) {
            return $this->errorResponse(
                'STORE_NOT_AVAILABLE',
                Yii::t('common', 'Выдача предметов на этот сервер недоступна. Нужен донат или не менее 10 часов на сервере.'),
                [],
                403
            );
        }

        // Вайп-блок (как в ChatServer)
        if (DropBlocked::getBlocked($userDrop->drop_id, $server->id, true)) {
            return $this->errorResponse('BLOCKED', Yii::t('common', 'Товар в вайп-блоке!'), [], 400);
        }

        $drop = $userDrop->dropOne ?: Drop::findOne($userDrop->drop_id);
        if (!$drop) {
            return $this->errorResponse('DROP_NOT_FOUND', Yii::t('common', 'Предмет не найден!'), [], 404);
        }

        // Блокировка на время обработки (как в ChatServer)
        $lockKey = 'userDrop_lock_' . $userDrop->id;
        if (Yii::$app->cache->get($lockKey)) {
            return $this->errorResponse(
                'IN_PROGRESS',
                Yii::t('common', 'Предмет уже обрабатывается, подождите немного!'),
                [],
                400
            );
        }
        Yii::$app->cache->set($lockKey, true, 10);

        $userDrop->refresh();
        if ($userDrop->status !== UserDrop::STATUS_ACTIVE) {
            Yii::$app->cache->delete($lockKey);
            return $this->errorResponse(
                'INVALID_STATUS',
                Yii::t('common', 'Товар уже был выведен или недоступен!'),
                [],
                400
            );
        }

        $userDrop->status = UserDrop::STATUS_WAIT;
        $userDrop->save(false);

        $rconUrl = Yii::$app->settings->get('site_rconUrl');
        if (empty($rconUrl) || !Yii::$app->has('curl')) {
            $userDrop->status = UserDrop::STATUS_ACTIVE;
            $userDrop->save(false);
            Yii::$app->cache->delete($lockKey);
            return $this->errorResponse(
                'CONFIG_ERROR',
                Yii::t('common', 'Произошла ошибка, попробуйте позже!'),
                [],
                503
            );
        }

        $isBlockedBuilding = $drop->is_blocked_building ? 'true' : 'false';
        $command = "store.take {$user->steam_id} {$userDrop->id} {$isBlockedBuilding}";

        try {
            $response = (Yii::$app->curl)
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setRawPostData(json_encode(['server' => $server->tag, 'command' => $command]))
                ->post($rconUrl . '/send');
        } catch (\Throwable $e) {
            $userDrop->status = UserDrop::STATUS_ACTIVE;
            $userDrop->save(false);
            Yii::$app->cache->delete($lockKey);
            return $this->errorResponse(
                'RCON_ERROR',
                Yii::t('common', 'Произошла ошибка, попробуйте позже!'),
                [],
                502
            );
        }

        $rconTask = new RconTasks();
        $rconTask->status = RconTasks::STATUS_DONE;
        $rconTask->command = $command;
        $rconTask->result = $response;
        $rconTask->server_tag = $server->tag;
        $rconTask->created_at = date('Y-m-d H:i:s');
        $rconTask->save(false);

        if (empty($response)) {
            $userDrop->status = UserDrop::STATUS_ACTIVE;
            $userDrop->save(false);
            Yii::$app->cache->delete($lockKey);
            return $this->errorResponse(
                'RCON_FAIL',
                Yii::t('common', 'Произошла ошибка, попробуйте позже!'),
                [],
                502
            );
        }

        $decoded = null;
        try {
            $outer = json_decode($response, true);
            $decoded = isset($outer['result']) ? json_decode($outer['result'], true) : null;
        } catch (\Throwable $e) {
            // ignore
        }

        if (!is_array($decoded) || !isset($decoded['success'])) {
            $userDrop->status = UserDrop::STATUS_ACTIVE;
            $userDrop->save(false);
            Yii::$app->cache->delete($lockKey);
            return $this->errorResponse(
                'RCON_FAIL',
                Yii::t('common', 'Произошла ошибка, попробуйте позже!'),
                [],
                502
            );
        }

        if (!empty($decoded['success'])) {
            $userDrop->status = UserDrop::STATUS_SENDED;
            $userDrop->sended_at = date('Y-m-d H:i:s');
            $userDrop->save(false);
            Yii::$app->cache->delete($lockKey);
            return $this->successResponse([
                'message' => Yii::t('common', 'Товар успешно получен!'),
                'itemId' => $userDrop->id,
            ]);
        }

        $userDrop->status = UserDrop::STATUS_ACTIVE;
        $userDrop->save(false);
        Yii::$app->cache->delete($lockKey);
        $errorMessage = isset($decoded['error']) ? $decoded['error'] : Yii::t('common', 'Произошла ошибка, попробуйте позже!');
        return $this->errorResponse('RCON_REJECT', $errorMessage, [], 400);
    }

    /**
     * Возврат предмета (продажа обратно)
     * 
     * @OA\Post(
     *     path="/v1/store/return",
     *     operationId="returnItem",
     *     tags={"Store"},
     *     summary="Вернуть предмет (продать обратно)",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"itemId"},
     *                 @OA\Property(property="itemId", type="integer", example=123, description="ID предмета (UserDrop ID)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Предмет возвращен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры или предмет не подлежит возврату"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Предмет не найден")
     * )
     */
    public function actionReturn()
    {
        $user = $this->getCurrentUser();
        $post = Yii::$app->request->post();

        $itemId = $post['itemId'] ?? null;

        if (empty($itemId)) {
            return $this->errorResponse('INVALID_DATA', 'Не указан ID предмета', [], 400);
        }

        $userDrop = UserDrop::findOne($itemId);
        if (!$userDrop || $userDrop->user_id !== $user->id) {
            return $this->errorResponse('ITEM_NOT_FOUND', 'Предмет не найден', [], 404);
        }

        if (!empty($userDrop->box_id) || !empty($userDrop->sets_id) || !empty($userDrop->parent_drop_id)) {
            return $this->errorResponse('CANNOT_RETURN', 'Предмет не подлежит возврату', [], 400);
        }

        if ($userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return $this->errorResponse('INVALID_STATUS', 'Предмет не доступен для возврата', [], 400);
        }

        $userBalance = $user->getPersonalBalance();
        $drop = $userDrop->dropOne;
        if (!$drop) {
            $drop = \common\models\box\Drop::findOne($userDrop->drop_id);
        }
        if (!$drop) {
            return $this->errorResponse('DROP_NOT_FOUND', 'Предмет не найден', [], 404);
        }

        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_SELL_DROP;
        $profit->amount = $drop->getRealPrice(false);
        $profit->user_balance_id = $userBalance->id;
        $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
            'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
        ]);
        $profit->created_at = date('Y-m-d H:i:s');
        if (!$profit->save(false)) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении возврата', [], 500);
        }

        $userDrop->status = UserDrop::STATUS_SELL;
        if (!$userDrop->save(false)) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при обновлении статуса', [], 500);
        }

        if (Yii::$app->has('queueProcess')) {
            Yii::$app->queueProcess->push(new ReturnDropJob(['userDrop' => $userDrop]));
        }

        return $this->successResponse([
            'message' => 'Предмет успешно возвращен',
            'itemId' => $userDrop->id,
        ]);
    }
}































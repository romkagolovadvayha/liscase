<?php

namespace api\controllers\v1;

use Yii;
use yii\web\BadRequestHttpException;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\profit\Profit;
use common\models\servers\Servers;
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

        $server = Servers::findOne($serverId);
        if (!$server) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден', [], 404);
        }

        // TODO: Реализовать логику доставки предмета на сервер
        // Это требует интеграции с RCON или другой системой доставки
        // Пока что просто помечаем как отправленный
        $userDrop->status = UserDrop::STATUS_SENDED;
        $userDrop->sended_at = date('Y-m-d H:i:s');
        
        if ($userDrop->save(false)) {
            return $this->successResponse([
                'message' => 'Предмет успешно выдан на сервер',
                'itemId' => $userDrop->id,
            ]);
        } else {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении', [], 500);
        }
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

        // Выполняем возврат (продажу)
        $userBalance = $user->getPersonalBalance();
        
        // Продаем все предметы в UserDrop
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->getRealPrice(false);
            $profit->user_balance_id = $userBalance->id;
            $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }

        $userDrop->status = UserDrop::STATUS_SELL;
        if ($userDrop->save(false)) {
            return $this->successResponse([
                'message' => 'Предмет успешно возвращен',
                'itemId' => $userDrop->id,
            ]);
        } else {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении', [], 500);
        }
    }
}











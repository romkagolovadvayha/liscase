<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\profit\Profit;
use common\components\queue\process\ReturnDropJob;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use common\components\web\AuthorizedControllerTrait;
use Yii;

class StoreController extends WebController
{
    use AuthorizedControllerTrait;

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
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $this->layout = '@frontend/views/layouts/launcher';
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->status === User::STATUS_BLOCKED) {
            throw new ForbiddenHttpException(Yii::t('common', 'Ваш аккаунт заблокирован!'));
        }
        return $this->render('index');
    }

    /**
     * Возврат товара через websocket
     * @return Response
     * @throws HttpException
     */
    public function actionReturn()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new ForbiddenHttpException(Yii::t('common', 'Требуется авторизация'));
        }

        $id = Yii::$app->request->post('id');
        if (empty($id)) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Не указан ID товара'),
            ];
        }

        $userDrop = UserDrop::findOne($id);
        if (empty($userDrop) || $userDrop->user_id !== $user->id) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Товар не найден'),
            ];
        }

        if (!empty($userDrop->box_id) || !empty($userDrop->sets_id) || !empty($userDrop->parent_drop_id)) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Не подлежит возврату!'),
            ];
        }

        if ($userDrop->status !== UserDrop::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Не найдена вещь в корзине!'),
            ];
        }

        // Выполняем возврат
        $userBalance = $user->getPersonalBalance();
        $this->_sellUserDrop($userDrop, $userBalance->id);

        // Отправляем через websocket
        Yii::$app->queueProcess->push(new ReturnDropJob(['userDrop' => $userDrop]));

        return [
            'success' => true,
            'message' => Yii::t('common', 'Предмет успешно возвращен!'),
            'id' => $userDrop->id,
        ];
    }

    /**
     * Продажа товара (возврат)
     * @param UserDrop $userDrop
     * @param int $userBalanceId
     */
    private function _sellUserDrop($userDrop, $userBalanceId)
    {
        // Используем dropOne для получения одного предмета
        $drop = $userDrop->dropOne;
        
        if (empty($drop)) {
            // Если dropOne не найден, пытаемся загрузить через drop_id
            $drop = \common\models\box\Drop::findOne($userDrop->drop_id);
        }
        
        if (empty($drop)) {
            Yii::error("Drop not found for UserDrop ID: {$userDrop->id}, drop_id: {$userDrop->drop_id}", __METHOD__);
            throw new \Exception('Предмет не найден');
        }
        
        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_SELL_DROP;
        $profit->amount = $drop->getRealPrice(false);
        $profit->user_balance_id = $userBalanceId;
        $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
            'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
        ], 'ru-RU');
        $profit->created_at = date('Y-m-d H:i:s');
        
        if (!$profit->save(false)) {
            Yii::error("Failed to save profit for UserDrop ID: {$userDrop->id}, errors: " . json_encode($profit->getErrors()), __METHOD__);
            throw new \Exception('Ошибка при сохранении возврата');
        }
        
        $userDrop->status = UserDrop::STATUS_SELL;
        if (!$userDrop->save(false)) {
            Yii::error("Failed to save UserDrop ID: {$userDrop->id}, errors: " . json_encode($userDrop->getErrors()), __METHOD__);
            throw new \Exception('Ошибка при обновлении статуса товара');
        }
    }
}

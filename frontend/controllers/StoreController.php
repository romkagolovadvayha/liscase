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
        
        // Временно отключено - технические работы
        $this->title = Yii::t('common', "Технические работы");
        return $this->renderContent('
            <div class="store_launcher" style="display: flex; align-items: center; justify-content: center; min-height: 60vh; text-align: center; padding: 40px 20px;">
                <div style="max-width: 600px;">
                    <div style="font-size: 64px; margin-bottom: 20px;">🔧</div>
                    <h1 style="font-size: 32px; margin-bottom: 20px; color: #fff;">' . Yii::t('common', 'Технические работы') . '</h1>
                    <p style="font-size: 18px; color: #ccc; line-height: 1.6;">
                        ' . Yii::t('common', 'Магазин временно недоступен. Мы проводим технические работы. Пожалуйста, зайдите позже.') . '
                    </p>
                </div>
            </div>
        ');
        
        // Раскомментировать для восстановления работы:
        // return $this->render('index');
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
        /** @var \common\models\box\Drop $drop */
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->getRealPrice(false);
            $profit->user_balance_id = $userBalanceId;
            $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        $userDrop->status = UserDrop::STATUS_SELL;
        $userDrop->save(false);
    }
}

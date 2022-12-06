<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\user\UserDrop;
use frontend\forms\profile\ProfileForm;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class UserController extends WebController
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

//    /**
//     * @param $id
//     *
//     * @return \yii\web\Response | string
//     * @throws NotFoundHttpException
//     */
//    public function actionBox($id)
//    {
//        $userBox = UserBox::findOne($id);
//        if (empty($userBox)) {
//            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
//        }
//        return $this->render('box', [
//            'userBox' => $userBox
//        ]);
//    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionInventory()
    {
        $post = Yii::$app->request->post();
        if (!empty($post['sell'])) {
            $userBalance = Yii::$app->user->identity->getPersonalBalance();
            if ($post['sell'] === 'all') {
                $userDrops = UserDrop::find()
                                     ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
                                     ->all();
                if (empty($userDrops)) {
                    throw new HttpException(402, Yii::t('common', 'Не найдены вещи в инвенторе!'));
                }
                /** @var UserDrop[] $userDrops */
                foreach ($userDrops as $userDrop) {
                    $this->_sellUserDrop($userDrop, $userBalance->id);
                }
            } else {
                $userDrop = UserDrop::findOne($post['sell']);
                if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                    throw new HttpException(402, Yii::t('common', 'Не найдена вещь в инвенторе!'));
                }
                $this->_sellUserDrop($userDrop, $userBalance->id);
            }
        }
        return $this->render('inventory');
    }

    /**
     * @param UserDrop $userDrop
     */
    private function _sellUserDrop($userDrop, $userBalanceId) {
        /** @var Drop $drop */
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->priceCeil;
            $profit->user_balance_id = $userBalanceId;
            $profit->comment = Yii::t('common', 'Продажа предметов', [], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        $userDrop->status = UserDrop::STATUS_SELL;
        $userDrop->save(false);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionPartner()
    {
        return $this->render('partner');
    }

    /**
     * @return string
     */
    public function actionProfile()
    {
        $user = Yii::$app->user->identity;
        $model = ProfileForm::findOne($user->userProfile->id);

        if ($model->load(Yii::$app->request->post())) {
            if ($model->saveRecord()) {
                Yii::$app->session->addFlash('success', 'Профиль успешно сохранен!');
            }
        }

        return $this->render('profile', [
            'model' => $model
        ]);
    }

    /**
     * @return string
     */
    public function actionPayment()
    {
        return $this->render('payment');
    }


    public function actionGetBalance()
    {
        $result = [
            'balanceStr' => Yii::$app->user->identity->getPersonalBalance()->getBalanceFormat(),
            'balance' => Yii::$app->user->identity->getPersonalBalance()->balanceCeil
        ];
        header("Content-Type: application/json");
        return json_encode($result);
    }
}

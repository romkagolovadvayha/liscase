<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Box;
use common\models\invoice\Invoice;
use common\models\promocode\Promocode;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserPromocode;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;

class BoxController extends WebController
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
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionBuyContainer($id)
    {
        $this->layout = 'service';
        $box = Box::findOne($id);
        if (empty($box)) {
            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
        }
        $post = Yii::$app->request->post();
        if (!empty($post['buy'])) {
            $user = Yii::$app->user->identity;
            $balance = $user->getPersonalBalance();
            if ($box->getPriceFinal() > $balance->balanceCeil) {
                throw new HttpException(402, Yii::t('common', 'Недостаточно средств на счете!'));
            }
            if ($box->type === Box::TYPE_FREE && !empty(Box::getNextOpenFreeBoxDate())) {
                throw new HttpException(402, Yii::t('common', 'Бесплатный кейс не доступен!'));
            }
            if ($box->getPriceFinal() > 0) {
                $promocode = Promocode::getActivePromocode();
                if (!empty($promocode)) {
                    UserPromocode::createRecord($user->id, $promocode->id);
                }
                Invoice::createRecord($user->id, $box->getPriceFinal(), null, $box->id);
            }
            $userBoxId = UserBox::createRecord($user->id, $box->id);
            $userBox = UserBox::findOne($userBoxId);
            [$boxDropCarousel, $number] = $userBox->box->_getDropFinal();
            $userBox->status = UserBox::STATUS_OPENED;
            $userBox->save();
            UserDrop::createRecord($user->id, $boxDropCarousel[$number]->drop->id, $box->id, UserDrop::STATUS_ACTIVE, false);

            return $this->render('../widgets/_roulete', [
                'boxDropCarousel' => $boxDropCarousel,
                'number' => $number,
            ]);
        }
    }

    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $box = Box::findOne($id);
        if (empty($box)) {
            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
        }
        return $this->render('view', [
            'box' => $box
        ]);
    }

}

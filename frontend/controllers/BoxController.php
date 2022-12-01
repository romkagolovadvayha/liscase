<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Box;
use common\models\invoice\Invoice;
use common\models\user\UserBox;
use common\models\user\UserDrop;
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
            if ($box->price > $balance->balance && $box->type !== Box::TYPE_FREE) {
                throw new HttpException(402, Yii::t('common', 'Недостаточно средств на счете!'));
            }
            if ($box->type !== Box::TYPE_FREE) {
                Invoice::createRecord($user->id, $box->price, null, $box->id);
            }
            $userBoxId = UserBox::createRecord($user->id, $box->id);
            $userBox = UserBox::findOne($userBoxId);
            [$boxDropCarousel, $number] = $this->_getDrop($userBox);
            if ($boxDropCarousel[$number]->drop->price > 1000) {
                [$boxDropCarousel, $number] = $this->_getDrop($userBox);
            }
            if ($boxDropCarousel[$number]->drop->price > 2000) {
                [$boxDropCarousel, $number] = $this->_getDrop($userBox);
            }
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
     * @param $userBox
     *
     * @return array
     */
    private function _getDrop($userBox) {
        $boxDropCarousel = $userBox->box->boxDropCarousel;
        $number = rand(count($boxDropCarousel) / 2, count($boxDropCarousel) - 1);

        return [$boxDropCarousel, $number];
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

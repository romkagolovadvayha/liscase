<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\invoice\Invoice;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserPromocode;
use yii\base\BaseObject;
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
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Контейнер не найден!'));
            return $this->redirect('/');
        }
        $post = Yii::$app->request->post();
        if (!empty($post['buy'])) {
            $user = Yii::$app->user->identity;
            $balance = $user->getPersonalBalance();
            if ($box->getPriceFinal() > $balance->balanceCeil) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Недостаточно средств на счете!'));
                return $this->redirect('/');
            }
            if ($box->type === Box::TYPE_FREE && !empty(Box::getNextOpenFreeBoxDate())) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Кейс не доступен!'));
                return $this->redirect('/');
            }
            if ($box->getPriceFinal() > 0) {
                Invoice::createRecord($user->id, $box->getPriceFinal(), null, $box->id);
            }
            $userBoxId = UserBox::createRecord($user->id, $box->id);
            $userBox = UserBox::findOne($userBoxId);
            [$boxDropCarousel, $number] = $userBox->box->_getDropFinal();
            $userBox->status = UserBox::STATUS_OPENED;
            $userBox->save();
            /** @var Drop $drop */
            $dropName =  Yii::t('database', $boxDropCarousel[$number]['boxDrop']->drop->name);
            $dropCount =  $boxDropCarousel[$number]['count'];
            $dropImage =  $boxDropCarousel[$number]['boxDrop']->drop->imageOrig->getImagePubUrl();
            if ($boxDropCarousel[$number]['boxDrop']->drop->id != 843) {
                UserDrop::createRecord($user->id, $boxDropCarousel[$number]['boxDrop']->drop->id, $box->id, null,UserDrop::STATUS_ACTIVE, false, $boxDropCarousel[$number]['count']);
            } else {
                $userBalance = Yii::$app->user->identity->getPersonalBalance();
                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_SELL_DROP;
                $profit->amount = $boxDropCarousel[$number]['count'];
                $profit->user_balance_id = $userBalance->id;
                $profit->comment = Yii::t('common', 'Выигрыш в бесплатной рулетке', [], 'ru-RU');
                $profit->created_at = date('Y-m-d H:i:s');
                $profit->save(false);
            }

            $block = "<div class='box_alert'>" .
                "<img class='box_alert_image' src='{$dropImage}'/>" .
                "<div class='box_alert_body'>" .
                "<div class='box_alert_body_title'>" . Yii::t('common', 'Награда получена') . "</div>" .
                "<div class='box_alert_body_descrption'>{$dropName} <b>x{$dropCount}</b></div>" .
                "</div>" .
                "</div>";


            Yii::$app->session->addFlash('success-box', $block);
            return $this->redirect('/');
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
        $this->layout = 'service';
        $box = Box::findOne($id);
        if (empty($box)) {
            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
        }
        return $this->render('view', [
            'box' => $box
        ]);
    }

}

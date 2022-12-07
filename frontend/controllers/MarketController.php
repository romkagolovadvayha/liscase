<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Drop;
use common\models\invoice\Invoice;
use common\models\user\UserDrop;
use frontend\forms\market\BuyForm;
use frontend\models\box\DropSearch;
use yii\base\BaseObject;
use yii\bootstrap5\LinkPager;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;

class MarketController extends WebController
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
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $searchModel = new DropSearch();
//        print_r(Yii::$app->request->queryParams);exit;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }


    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $drop = Drop::findOne($id);
        if (empty($drop)) {
            throw new NotFoundHttpException(Yii::t('common', 'Предмет не найден!'));
        }
        if (!empty($_POST['buy'])) {
            $user = Yii::$app->user->identity;
            $balance = $user->getPersonalBalance();
            if ($drop->getPriceMarket() > $balance->balanceCeil) {
                throw new HttpException(402, Yii::t('common', 'Недостаточно средств на счете!'));
            }
            Invoice::createRecord($user->id, $drop->getPriceMarket(), Invoice::TYPE_PAYMENT_MARKET_DROP);
            UserDrop::createRecord($user->id, $drop->id, null, UserDrop::STATUS_ACTIVE, false);
            Yii::$app->session->addFlash('success', 'Предмет успешно приобретен!');
        }
        return $this->render('view', [
            'drop' => $drop
        ]);
    }

}

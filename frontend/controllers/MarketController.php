<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Drop;
use frontend\forms\market\BuyForm;
use frontend\models\box\DropSearch;
use yii\base\BaseObject;
use yii\bootstrap5\LinkPager;
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
        return $this->render('view', [
            'drop' => $drop
        ]);
    }

}

<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use Yii;

class ReferralController extends WebController
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
        $this->view->params['page'] = 'referral';
        return $this->render('referral');
    }

}

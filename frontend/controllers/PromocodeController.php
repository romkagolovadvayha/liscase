<?php

namespace frontend\controllers;

use common\components\web\Cookie;
use common\controllers\WebController;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use frontend\forms\promocode\PromocodeForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Response;

class PromocodeController extends WebController
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
     * @return string|Response
     */
    public function actionClear()
    {
        Cookie::remove('promocode');
        return $this->redirect(Yii::$app->homeUrl);
    }

}

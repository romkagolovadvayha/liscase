<?php

namespace frontend\controllers;

use common\models\user\User;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use Yii;

class WorkedController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string|Response
     */
    public function actionIndex()
    {
        $this->layout = 'service';

        if (!Yii::$app->settings->get('site_enabled')) {
            return $this->redirect('/');
        }
        return $this->render('../site/worked.twig');
    }

}

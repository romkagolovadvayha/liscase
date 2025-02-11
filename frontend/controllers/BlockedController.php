<?php

namespace frontend\controllers;

use common\models\user\User;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use Yii;

class BlockedController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string|Response
     */
    public function actionIndex()
    {
        if (Yii::$app->user->isGuest || Yii::$app->user->identity->status !== User::STATUS_BLOCKED) {
            return $this->redirect('/');
        }
        return $this->render('blocked.twig');
    }

}

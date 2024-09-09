<?php

namespace frontend\controllers;

use Yii;
use common\controllers\WebController;
use common\models\user\UserConfirmCode;

class BotController extends WebController
{
    public function actionActivate()
    {
        $userConfirmModel = UserConfirmCode::createTypeTelegramBot(Yii::$app->user->id);

        return $this->render('activate', [
            'userConfirmModel' => $userConfirmModel,
            'isNew'            => Yii::$app->request->get('isNew'),
        ]);
    }
}
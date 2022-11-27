<?php

namespace common\components\web;

use Yii;
use yii\base\BootstrapInterface;

class UserBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if (Yii::$app->user->isGuest) {
            (new Language())->setDefaultLanguage();
        }

        // редирект на заглушку, когда производится обновление системы
//        if (!strstr(Yii::$app->request->url, '/site/notification-page')) {
//            return Yii::$app->response->redirect('/site/notification-page');
//        }
    }
}
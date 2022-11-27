<?php

namespace frontend\controllers;

use Yii;
use common\components\web\Cookie;
use common\components\helpers\Role;
use common\models\user\User;

class AuthController extends \common\controllers\AuthController
{
    public function actionLogout()
    {
        $adminUserId = Cookie::getValue('fromSwitcherUserId');
        Cookie::remove('fromSwitcherUserId');

        Yii::$app->user->logout();

        if (!empty($adminUserId)) {
            $user = User::findOne($adminUserId);
            Yii::$app->user->switchIdentity($user);

            if ($user->isAccessBackend()) {
                $url = '/user/index';
                if ($user->getRole() == Role::ROLE_CONTENT_MANAGER) {
                    $url = '/marketing/user/index';
                }

                return $this->redirect(Yii::$app->params['backendUrl'] . $url);
            }

            return $this->redirect('/');
        }

        return $this->goHome();
    }
}

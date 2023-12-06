<?php

namespace console\controllers;

use common\models\box\Box;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\console\Controller;

class UserController extends Controller
{
    /**
     * Указать пароль для пользователя
     * user/set-password
     *
     * @throws \Exception
     */
    public function actionSetPassword($id, $password)
    {
        $user = User::findOne($id);
        $user->setPassword($password);
        $user->save(false);
    }

}

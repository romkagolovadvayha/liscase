<?php

namespace console\controllers;

use common\models\box\Box;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserTree;
use yii\base\BaseObject;
use yii\console\Controller;

class UserTreeController extends Controller
{
    /**
     * user-tree/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        $users = User::find()
            ->all();
        foreach ($users as $user) {
            UserTree::appendUser($user->id, 509);
        }
    }

}

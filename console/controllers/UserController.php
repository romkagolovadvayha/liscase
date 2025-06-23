<?php

namespace console\controllers;

use common\components\queue\process\UserSteamInfoUpdateJob;
use common\models\box\Box;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserTree;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\db\StaleObjectException;

class UserController extends Controller
{
    /**
     * user/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var User[] $users */
        $users = User::find()
            ->orderBy(['id' => SORT_DESC])
            ->limit(40)
            ->all();
        foreach ($users as $user) {
            \Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->steam_id]));
        }
    }

}

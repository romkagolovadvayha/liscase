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
use yii\db\StaleObjectException;

class UserTreeController extends Controller
{
    /**
     * user-tree/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var User[] $users */
        $users = User::find()
            ->all();
        foreach ($users as $user) {
            if (empty($user->getParentUser())) {
                UserTree::appendUser($user->id, 509);
            }
        }
    }

    /**
     * user-tree/change-tree USER_ID TO_USER_ID
     *
     * @param $userId
     * @param $toUserId
     *
     * @throws StaleObjectException
     */
    public function actionChangeTree($userId, $toUserId)
    {
        /** @var User $user */
        $user = User::findOne($userId);

        /** @var UserTree[] $childrenUserTrees */
        $childrenUserTrees = [];
        if ($user->userTree) {
            $childrenUserTrees = $user
                ->getChildrenUserTreeQuery()
                ->all();
        }

        foreach ($childrenUserTrees as $childrenUserTree) {
            echo $childrenUserTree->level . ' - ' . $childrenUserTree->user_id . ' - '
                . $childrenUserTree->parent_user_id . PHP_EOL;
        }

        if (!empty($user->userTree)) {
            $user->userTree->delete();
        }

        UserTree::appendUser($userId, $toUserId);

        foreach ($childrenUserTrees as $childrenUserTree) {
            $childrenUserId       = $childrenUserTree->user_id;
            $childrenParentUserId = $childrenUserTree->parent_user_id;

            $childrenUserTree->delete();
            UserTree::appendUser($childrenUserId, $childrenParentUserId);
        }
    }

}

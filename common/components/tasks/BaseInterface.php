<?php

namespace common\components\tasks;

use common\models\tasks\Tasks;
use common\models\user\User;
use common\models\user\UserTasks;
use Yii;

interface BaseInterface
{
    /**
     * @param $taskId
     * @param $userId
     *
     * @return UserTasks
     */
    public function check($taskId, $userId);
    /**
     * @param $taskId
     * @param $userId
     *
     * @return UserTasks
     */
    public function profit($taskId, $userId);

    /**
     * @param User $user
     * @param Tasks $task
     *
     * @return bool
     */
    public function visability($user, $task);

    /**
     * @return int
     */
    public function autoSuccessTime();

    /**
     * @return int
     */
    public function autoRejectTime();

    /**
     * @return string|null
     */
    public function getRedirectSuccess();

}

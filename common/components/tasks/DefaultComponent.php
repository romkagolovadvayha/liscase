<?php

namespace common\components\tasks;

use common\models\user\UserTasks;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;

class DefaultComponent extends BaseClass
{
    public function visability($user, $task) {
        return true;
    }

    public function check($taskId, $userId): UserTasks
    {
        return $this->updateUserTaskStatus($taskId, $userId, UserTasks::STATUS_WAITING);
    }

    /**
     * Принимать автоматически задание через это время
     */
    public function autoSuccessTime() {
        return 60 * 60 * 3;
    }

}

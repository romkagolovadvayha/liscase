<?php

namespace common\components\tasksPublishPlace;

use common\models\user\User;
use common\models\user\UserTasks;
use Yii;

interface BaseInterface
{

    /**
     * @param User $user
     *
     * @return bool
     */
    public function visability($user);

}

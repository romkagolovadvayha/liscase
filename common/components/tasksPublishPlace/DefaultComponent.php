<?php

namespace common\components\tasksPublishPlace;

use common\models\user\UserTasks;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;

class DefaultComponent extends BaseClass
{
    public function visability($user) {
        return true;
    }

}

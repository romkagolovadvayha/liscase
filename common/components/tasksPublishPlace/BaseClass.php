<?php

namespace common\components\tasksPublishPlace;

use common\models\profit\Profit;
use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;

abstract class BaseClass implements BaseInterface
{
    public function visability($user) {
        return true;
    }

}

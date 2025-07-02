<?php

namespace common\components\tasksPublishPlace;

use common\models\tasks\TasksPublishPlace;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class TasksPublishPlaceComponent extends Component
{

    const INVESTORS = 2;

    /**
     * @param int $type
     *
     * @return BaseInterface
     * @throws \Exception
     */
    public static function getInstance($type)
    {
        $classMap = [
//            TasksPublishPlaceComponent::INVESTORS => InvestorProject::class,
        ];

        $className = ArrayHelper::getValue($classMap, $type);
        if (empty($className)) {
            return new DefaultComponent();
        }

        return new $className;
    }
}

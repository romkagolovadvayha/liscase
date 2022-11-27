<?php

namespace common\components\helpers;

use Yii;

class UserQueryHelper
{
    /**
     * @param string $field
     * @param bool $inCondition
     *
     * @return array
     */
    public static function getIgnoreUsers($field = 'user_id', $inCondition = false)
    {
        $condition = 'NOT IN';
        if ($inCondition) {
            $condition = 'IN';
        }

        return [$condition, $field, self::getIgnoreUsersArray()];
    }

    /**
     * @return array
     */
    public static function getIgnoreUsersArray()
    {
        $array = range(1, 19);

        $array[] = 23;
        $array[] = 24;
        $array[] = 26;

        return $array;
    }

    public static function addIdCondition($query)
    {
        if (Yii::$app->id == 'app-backend' || Yii::$app->id == 'app-console') {
            $query->andWhere(['NOT IN', 't.id', Yii::$app->params['idLocal']]);
        }
    }
}
<?php

namespace common\models\user;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[\common\models\UserTree]].
 *
 * @see UserTree
 * @method UserTree[]|array all($db = null)
 * @method UserTree|array|null one($db = null)
 */
class UserTreeQuery extends ActiveQuery
{
    public function behaviors()
    {
        return [
            \creocoder\nestedsets\NestedSetsQueryBehavior::class,
        ];
    }
}
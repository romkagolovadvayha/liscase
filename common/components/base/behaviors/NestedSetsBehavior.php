<?php

namespace common\components\base\behaviors;

use Yii;

class NestedSetsBehavior extends \creocoder\nestedsets\NestedSetsBehavior
{
//    /**
//     * Gets the parents of the node.
//     * @param integer|null $depth the depth
//     * @return \yii\db\ActiveQuery
//     */
//    public function parents($depth = null)
//    {
//        $condition = [
//            'and',
//            ['<', $this->leftAttribute, $this->owner->getAttribute($this->leftAttribute)],
//            ['>', $this->rightAttribute, $this->owner->getAttribute($this->rightAttribute)],
//        ];
//
//        if ($depth !== null) {
//            $condition[] = ['>=', $this->depthAttribute, $this->owner->getAttribute($this->depthAttribute) - $depth];
//        }
//
//        $this->applyTreeAttributeCondition($condition);
//
//        return $this->owner->find()->andWhere($condition)->addOrderBy([$this->leftAttribute => SORT_DESC]);
//    }
}
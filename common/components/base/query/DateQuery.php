<?php

namespace common\components\base\query;

use yii\db\ActiveQuery;
use yii\db\Query;

class DateQuery
{
    /**
     * @param ActiveQuery|Query $query
     * @param             $model
     * @param string      $field
     * @param string      $attribute
     */
    public static function addDateCondition($query, $model, $field = 'created_at', $attribute = null)
    {
        if (empty($attribute)) {
            $exploded  = explode('.', $field);
            $attribute = end($exploded);
        }

        if (empty($model->{$attribute})) {
            return;
        }

        list($dateFrom, $dateTo) = explode(' - ', $model->{$attribute});

        $query->andFilterWhere([
            'BETWEEN',
            $field,
            date('Y-m-d 00:00:00', strtotime($dateFrom)),
            date('Y-m-d 23:59:59', strtotime($dateTo)),
        ]);
    }
}
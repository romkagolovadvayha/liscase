<?php

namespace common\models\box;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;
use Yii;

class SetsDropSearch extends SetsDrop
{

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, ?callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'id'       => $this->id,
            ]);

        DateQuery::addDateCondition($query, $this, 'created_at');
        return new ActiveDataProvider([
            'query'      => $query,
            'sort'       => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }
}

<?php

namespace common\models\news;

use Yii;
use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;

class NewsSearch extends News
{
    /**
     * @param array    $params
     * @param callable $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params = [], callable $filter = null): ActiveDataProvider
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query->andWhere(['!=', 'status', self::STATUS_DELETED]);

        $query->andFilterWhere([
            'id'         => $this->id,
            'status'     => $this->status,
        ]);

        DateQuery::addDateCondition($query, $this, 'created_at');
        DateQuery::addDateCondition($query, $this, 'date_published');

        return new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);
    }
}

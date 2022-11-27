<?php

namespace common\models\news;

use Yii;
use yii\data\ActiveDataProvider;
use common\components\base\query\DateQuery;

class NewsContentSearch extends NewsContent
{
    public function rules()
    {
        return [
            [['id', 'news_id', 'language', 'title', 'created_at'], 'safe'],
        ];
    }

    /**
     * @param array    $params
     * @param callable $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params = [], callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query->andFilterWhere([
            'id'       => $this->id,
            'news_id'  => $this->news_id,
            'language' => $this->language,
            'title'    => $this->title,
        ]);

        DateQuery::addDateCondition($query, $this, 'created_at');

        return new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);
    }
}

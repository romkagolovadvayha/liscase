<?php

namespace backend\models;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;

class TelegramConstructorSearch extends TelegramConstructor
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [
                [
                    'title',
                    'bot_id',
                    'audience_id',
                    'created_at',
                    'status',
                ],
                'trim',
            ],
        ];
    }

    public function init()
    {
        $this->status = 'all';
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, ?callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        $status = $this->status;
        if ($status == 'all') {
            $status = '';
        }

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'id'       => $this->id,
                'title'       => $this->title,
                'bot_id'       => $this->bot_id,
                'audience_id'       => $this->audience_id,
                'status'       => $status,
            ]);

        DateQuery::addDateCondition($query, $this, 'created_at');
        return new ActiveDataProvider([
            'query'      => $query,
            'sort'       => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
    }
}

<?php

namespace backend\models;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;

class TelegramRecipientsSearch extends TelegramRecipients
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [
                [
                    'name',
                    'ref_id',
                    'created_at',
                ],
                'trim',
            ],
            [['ref_id', 'quantity'], 'safe',],
        ];
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, callable $filter = null)
    {
        $this->load($params);

        $query = self::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        $query
            ->andFilterWhere([
                'id'       => $this->id,
                'name'     => $this->name,
                'quantity' => $this->quantity
            ])
            ;

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
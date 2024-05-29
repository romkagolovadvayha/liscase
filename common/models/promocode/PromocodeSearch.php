<?php

namespace common\models\promocode;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;
use Yii;

class PromocodeSearch extends Promocode
{
    public function rules(): array
    {
        return [
            [['id', 'code', 'status'], 'required'],
            [['status'], 'integer'],
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
                'status'     => $this->status,
            ])
            ->andFilterWhere(['LIKE', 'code', $this->code]);

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
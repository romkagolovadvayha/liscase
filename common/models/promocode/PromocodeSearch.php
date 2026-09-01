<?php

namespace common\models\promocode;

use common\components\base\query\DateQuery;
use yii\data\ActiveDataProvider;
use Yii;

class PromocodeSearch extends Promocode
{

    public $status = 1;

    /** @var string|null 'single' = только одноразовые, иначе обычные (многоразовые) */
    public $tab;

    public function rules(): array
    {
        return [
            [['id', 'code', 'status'], 'required'],
            [['status'], 'integer'],
            [['tab'], 'safe'],
        ];
    }

    /**
     * @param array $params
     * @param callable|null $filter
     * @return ActiveDataProvider
     */
    public function search(array $params, ?callable $filter = null)
    {
        $this->load($params);

        $query = Promocode::find();

        if (is_callable($filter)) {
            call_user_func($filter, $query);
        }

        if ($this->tab === 'single') {
            $query->andWhere(['is_single_use' => 1]);
        } else {
            $query->andWhere(['is_single_use' => 0]);
        }

        if ($this->id !== null && $this->id !== '') {
            $query->andWhere(['id' => $this->id]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => $this->status]);
        }
        if ($this->code !== null && $this->code !== '') {
            $query->andWhere(['LIKE', 'code', $this->code]);
        }

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

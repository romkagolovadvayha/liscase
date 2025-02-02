<?php

namespace frontend\models\banlist;

use common\components\base\query\DateQuery;
use common\models\bans\Bans;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\building\Building;

/**
 * BuildingSearch represents the model behind the search form of `common\models\bans\Bans`.
 */
class BansSearch extends Bans
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['reason', 'steam_id'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = BansSearch::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'       => [
                'defaultOrder' => ['banned_at' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }
        if (!empty($this->steam_id)) {
            $query->andFilterWhere(
                [
                    'OR',
                    ['LIKE', 'username', '%' . $this->steam_id . '%', false],
                    ['LIKE', 'steam_id', '%' . $this->steam_id . '%', false]
                ]
            );
        }
        $query->andFilterWhere([
            'OR',
            ['>=', 'unbanned_at', date('Y-m-d H:i:s')],
            'unbanned_at is NULL',
        ]);

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'user_id' => $this->user_id,
            'server_id' => $this->server_id,
        ]);

        $query->andFilterWhere(['like', 'reason', $this->reason]);

        DateQuery::addDateCondition($query, $this, 'banned_at');
        DateQuery::addDateCondition($query, $this, 'unbanned_at');

        return $dataProvider;
    }
}

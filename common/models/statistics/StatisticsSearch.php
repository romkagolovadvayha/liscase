<?php

namespace common\models\statistics;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\statistics\Statistics;

/**
 * StatisticsSearch represents the model behind the search form of `common\models\statistics\Statistics`.
 */
class StatisticsSearch extends Statistics
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'value'], 'integer'],
            [['steam_id', 'key', 'server_tag', 'wipe'], 'safe'],
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
        $query = Statistics::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'value' => $this->value,
        ]);

        $query->andFilterWhere(['like', 'steam_id', $this->steam_id])
            ->andFilterWhere(['like', 'key', $this->key])
            ->andFilterWhere(['like', 'server_tag', $this->server_tag])
            ->andFilterWhere(['like', 'wipe', $this->wipe]);

        return $dataProvider;
    }
}

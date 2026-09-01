<?php

namespace backend\models;

use common\components\base\query\DateQuery;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use backend\models\TelegramConstructorMessage;

/**
 * TelegramConstructorMessageSearch represents the model behind the search form of `backend\models\TelegramConstructorMessage`.
 */
class TelegramConstructorMessageSearch extends TelegramConstructorMessage
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['title', 'created_at'], 'safe'],
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
        $query = TelegramConstructorMessage::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
            'pagination' => ['pageSize' => 10],
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
        ]);
        DateQuery::addDateCondition($query, $this, 'created_at');

        $query->andFilterWhere(['like', 'title', $this->title]);

        return $dataProvider;
    }
}

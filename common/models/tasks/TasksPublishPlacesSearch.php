<?php

namespace common\models\tasks;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\tasks\TasksPublishPlace;

/**
 * TasksPublishPlacesSearch represents the model behind the search form of `common\models\tasks\TasksPublishPlace`.
 */
class TasksPublishPlacesSearch extends TasksPublishPlace
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'order_index'], 'integer'],
            [['title', 'created_at', 'description', 'system_check_code'], 'safe'],
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
        $query = TasksPublishPlace::find();

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
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
        ]);

        $query->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'system_check_code', $this->system_check_code]);

        return $dataProvider;
    }
}

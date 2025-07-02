<?php

namespace common\models\tasks;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\tasks\Tasks;

/**
 * TasksSearch represents the model behind the search form of `common\models\tasks\Tasks`.
 */
class TasksSearch extends Tasks
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'tasks_publish_place_id', 'tasks_projects_id', 'amount', 'is_email_field', 'is_check_method_auto', 'is_permanent', 'is_publish', 'order_index', 'promotion_id', 'is_archive'], 'integer'],
            [['image', 'name', 'short_name', 'date_start', 'date_end', 'description', 'amount_icon', 'additional_text', 'url_text', 'url_link', 'button_text', 'button_url', 'reward_amount_signature', 'additional_explanation', 'additional_url_text', 'additional_url_link', 'system_check_code', 'created_at', 'lk_lang', 'video_link'], 'safe'],
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
        $query = Tasks::find();

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
            'tasks_publish_place_id' => $this->tasks_publish_place_id,
            'tasks_projects_id' => $this->tasks_projects_id,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'amount' => $this->amount,
            'is_email_field' => $this->is_email_field,
            'is_check_method_auto' => $this->is_check_method_auto,
            'is_permanent' => $this->is_permanent,
            'is_publish' => $this->is_publish,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'promotion_id' => $this->promotion_id,
            'is_archive' => $this->is_archive,
        ]);

        $query->andFilterWhere(['like', 'image', $this->image])
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'short_name', $this->short_name])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'amount_icon', $this->amount_icon])
            ->andFilterWhere(['like', 'additional_text', $this->additional_text])
            ->andFilterWhere(['like', 'url_text', $this->url_text])
            ->andFilterWhere(['like', 'url_link', $this->url_link])
            ->andFilterWhere(['like', 'button_text', $this->button_text])
            ->andFilterWhere(['like', 'button_url', $this->button_url])
            ->andFilterWhere(['like', 'reward_amount_signature', $this->reward_amount_signature])
            ->andFilterWhere(['like', 'additional_explanation', $this->additional_explanation])
            ->andFilterWhere(['like', 'additional_url_text', $this->additional_url_text])
            ->andFilterWhere(['like', 'additional_url_link', $this->additional_url_link])
            ->andFilterWhere(['like', 'system_check_code', $this->system_check_code])
            ->andFilterWhere(['like', 'lk_lang', $this->lk_lang])
            ->andFilterWhere(['like', 'video_link', $this->video_link]);

        return $dataProvider;
    }
}

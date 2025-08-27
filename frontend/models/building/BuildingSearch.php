<?php

namespace frontend\models\building;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\building\Building;

/**
 * BuildingSearch represents the model behind the search form of `common\models\building\Building`.
 */
class BuildingSearch extends Building
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'status'], 'integer'],
            [['name', 'description', 'location', 'server_tag', 'created_at', 'server_tag'], 'safe'],
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
        $query = Building::find()
                         ->alias('b')
                         ->joinWith(['buildingLikes', 'buildingImage', 'server', 'user', 'user.server', 'user.userProfile'])
                         ->andWhere(['b.status' => Building::STATUS_ACTIVE]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'       => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 40,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'b.id' => $this->id,
            'b.user_id' => $this->user_id,
            'b.status' => $this->status,
            'b.created_at' => $this->created_at,
        ]);

        // <--- если выбран(ы) категории, фильтруем по IN
        if (!empty($this->server_tag)) {
            $query->andWhere(['in', 'b.server_tag', $this->server_tag]);
            // альтернативно: $query->andFilterWhere(['server_skin_category_id' => $this->server_skin_category_id]);
        }


        $query->andFilterWhere(['like', 'b.name', $this->name])
            ->andFilterWhere(['like', 'b.description', $this->description])
            ->andFilterWhere(['like', 'b.location', $this->location])
            ->andFilterWhere(['like', 'b.server_tag', $this->server_tag]);

        return $dataProvider;
    }
}

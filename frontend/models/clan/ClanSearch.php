<?php

namespace frontend\models\clan;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\clan\Clan;

/**
 * ClanSearch represents the model behind the search form of `common\models\clan\Clan`.
 */
class ClanSearch extends Clan
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'recruitment', 'user_id'], 'integer'],
            [['name', 'description', 'discord', 'vk', 'telegram', 'created_at'], 'safe'],
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
        $query = Clan::find();

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
            'recruitment' => $this->recruitment,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'discord', $this->discord])
            ->andFilterWhere(['like', 'vk', $this->vk])
            ->andFilterWhere(['like', 'telegram', $this->telegram]);

        return $dataProvider;
    }
}

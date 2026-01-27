<?php

namespace backend\models\map;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\map\MapList;

/**
 * MapListSearch represents the model behind the search form of `common\models\map\MapList`.
 */
class MapListSearch extends MapList
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'size_int', 'seed', 'save_version', 'total_monuments', 'land_percentage', 'islands', 'mountains', 'ice_lakes', 'rivers', 'lakes', 'canyons', 'oases', 'buildable_rocks'], 'integer'],
            [['is_staging', 'is_custom_map', 'can_download'], 'boolean'],
            [['hash', 'url', 'image', 'image_preview', 'size', 'map_type', 'raw_image_url', 'image_url', 'image_icon_url', 'thumbnail_url', 'created_at'], 'safe'],
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
        $query = MapList::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
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
            'id' => $this->id,
            'size_int' => $this->size_int,
            'seed' => $this->seed,
            'save_version' => $this->save_version,
            'total_monuments' => $this->total_monuments,
            'land_percentage' => $this->land_percentage,
            'islands' => $this->islands,
            'mountains' => $this->mountains,
            'ice_lakes' => $this->ice_lakes,
            'rivers' => $this->rivers,
            'lakes' => $this->lakes,
            'canyons' => $this->canyons,
            'oases' => $this->oases,
            'buildable_rocks' => $this->buildable_rocks,
            'is_staging' => $this->is_staging,
            'is_custom_map' => $this->is_custom_map,
            'can_download' => $this->can_download,
            'created_at' => $this->created_at,
        ]);

        $query->andFilterWhere(['like', 'hash', $this->hash])
            ->andFilterWhere(['like', 'url', $this->url])
            ->andFilterWhere(['like', 'image', $this->image])
            ->andFilterWhere(['like', 'image_preview', $this->image_preview])
            ->andFilterWhere(['like', 'size', $this->size])
            ->andFilterWhere(['like', 'map_type', $this->map_type])
            ->andFilterWhere(['like', 'raw_image_url', $this->raw_image_url])
            ->andFilterWhere(['like', 'image_url', $this->image_url])
            ->andFilterWhere(['like', 'image_icon_url', $this->image_icon_url])
            ->andFilterWhere(['like', 'thumbnail_url', $this->thumbnail_url]);

        return $dataProvider;
    }
}


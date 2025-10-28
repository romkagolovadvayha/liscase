<?php

namespace backend\models\radio;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\radio\RadioTrack;

/**
 * RadioTrackSearch represents the model behind the search form of `common\models\radio\RadioTrack`.
 */
class RadioTrackSearch extends RadioTrack
{
    public $userName;
    public $stationName;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'radio_station_id', 'user_id', 'duration', 'status', 'likes', 'plays'], 'integer'],
            [['title', 'artist', 'filename', 'created_at', 'userName', 'stationName'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            ['userName', 'stationName']
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // Use default scenario with all attributes
        return [Model::SCENARIO_DEFAULT => $this->attributes()];
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
        $query = RadioTrack::find()
            ->joinWith(['user', 'radioStation']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id',
                    'title',
                    'artist',
                    'status',
                    'likes',
                    'plays',
                    'created_at',
                    'userName' => [
                        'asc' => ['user.username' => SORT_ASC],
                        'desc' => ['user.username' => SORT_DESC],
                    ],
                    'stationName' => [
                        'asc' => ['radio_station.name' => SORT_ASC],
                        'desc' => ['radio_station.name' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'radio_track.id' => $this->id,
            'radio_track.radio_station_id' => $this->radio_station_id,
            'radio_track.user_id' => $this->user_id,
            'radio_track.duration' => $this->duration,
            'radio_track.status' => $this->status,
            'radio_track.likes' => $this->likes,
            'radio_track.plays' => $this->plays,
        ]);

        $query->andFilterWhere(['like', 'radio_track.title', $this->title])
            ->andFilterWhere(['like', 'radio_track.artist', $this->artist])
            ->andFilterWhere(['like', 'radio_track.filename', $this->filename])
            ->andFilterWhere(['like', 'user.username', $this->userName])
            ->andFilterWhere(['like', 'radio_station.name', $this->stationName])
            ->andFilterWhere(['like', 'radio_track.created_at', $this->created_at]);

        return $dataProvider;
    }
}


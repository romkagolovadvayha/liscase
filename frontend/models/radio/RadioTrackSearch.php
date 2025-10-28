<?php

namespace frontend\models\radio;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\radio\RadioTrack;

/**
 * RadioTrackSearch represents the model behind the search form for frontend users.
 */
class RadioTrackSearch extends RadioTrack
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['radio_station_id'], 'integer'],
            [['title', 'artist'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param int|null $stationId Filter by specific station
     *
     * @return ActiveDataProvider
     */
    public function search($params, $stationId = null)
    {
        $query = RadioTrack::find()
            ->where(['radio_track.status' => RadioTrack::STATUS_ACTIVE])
            ->joinWith(['user', 'radioStation']);

        if ($stationId) {
            $query->andWhere(['radio_track.radio_station_id' => $stationId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['likes' => SORT_DESC, 'id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 24,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'radio_station_id' => $this->radio_station_id,
        ]);

        $query->andFilterWhere(['like', 'radio_track.title', $this->title])
            ->andFilterWhere(['like', 'radio_track.artist', $this->artist]);

        return $dataProvider;
    }
}


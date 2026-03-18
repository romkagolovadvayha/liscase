<?php

namespace backend\models\video;

use common\models\video\UserVideo;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * UserVideoSearch represents the model behind the search form of `common\models\video\UserVideo`.
 */
class UserVideoSearch extends UserVideo
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'user_id', 'status'], 'integer'],
            [['name', 'type', 'video_link', 'created_at'], 'safe'],
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
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = UserVideo::find()->joinWith(['user']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'user_video.id' => $this->id,
            'user_video.user_id' => $this->user_id,
            'user_video.status' => $this->status,
            'user_video.created_at' => $this->created_at,
        ]);

        $query->andFilterWhere(['like', 'user_video.name', $this->name])
            ->andFilterWhere(['like', 'user_video.type', $this->type])
            ->andFilterWhere(['like', 'user_video.video_link', $this->video_link]);

        return $dataProvider;
    }
}

<?php

namespace backend\models;

use common\models\tournament\Tournament;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Поиск турниров в админке.
 */
class TournamentSearch extends Tournament
{
    /**
     * {@inheritdoc}
     */
    /** @var string Фаза: upcoming | active | past */
    public $phase = '';

    public function rules(): array
    {
        return [
            [['id', 'server_id'], 'integer'],
            [['title', 'slug', 'status', 'phase'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = Tournament::find()->with('server');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['sort' => SORT_DESC, 'starts_at' => SORT_DESC],
                'attributes' => ['id', 'title', 'starts_at', 'status', 'sort'],
            ],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['server_id' => $this->server_id]);
        $query->andFilterWhere(['status' => $this->status]);
        $query->andFilterWhere(['like', 'title', $this->title]);
        $query->andFilterWhere(['like', 'slug', $this->slug]);

        $phase = trim((string)$this->phase);
        if ($phase !== '' && in_array($phase, [Tournament::PHASE_UPCOMING, Tournament::PHASE_ACTIVE, Tournament::PHASE_PAST], true)) {
            $now = date('Y-m-d H:i:s');
            if ($phase === Tournament::PHASE_PAST) {
                $query->andWhere(['<', 'ends_at', $now]);
            } elseif ($phase === Tournament::PHASE_ACTIVE) {
                $query->andWhere(['<=', 'starts_at', $now])->andWhere(['>=', 'ends_at', $now]);
            } else {
                $query->andWhere(['>', 'starts_at', $now]);
            }
        }

        return $dataProvider;
    }
}

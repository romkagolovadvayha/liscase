<?php

namespace backend\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\servers\Servers;

/**
 * ServersSearch represents the model behind the search form of `common\models\servers\Servers`.
 */
class ServersSearch extends Servers
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'wipe_type', 'port', 'query', 'rcon', 'players', 'joined', 'queued', 'team_limit', 'max', 'status', 'stats_payment', 'skindrops', 'wargm_id'], 'integer'],
            [['name', 'wipe', 'next_wipe', 'global_wipe', 'description', 'rules', 'ip', 'rcon_password', 'map', 'db_host', 'db_name', 'db_user', 'db_password', 'tag', 'commands', 'discord_token'], 'safe'],
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
        $query = Servers::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['sort' => SORT_ASC],
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
            'wipe' => $this->wipe,
            'wipe_type' => $this->wipe_type,
            'next_wipe' => $this->next_wipe,
            'global_wipe' => $this->global_wipe,
            'port' => $this->port,
            'query' => $this->query,
            'rcon' => $this->rcon,
            'players' => $this->players,
            'joined' => $this->joined,
            'queued' => $this->queued,
            'team_limit' => $this->team_limit,
            'max' => $this->max,
            'status' => $this->status,
            'stats_payment' => $this->stats_payment,
            'skindrops' => $this->skindrops,
            'wargm_id' => $this->wargm_id,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'rules', $this->rules])
            ->andFilterWhere(['like', 'ip', $this->ip])
            ->andFilterWhere(['like', 'rcon_password', $this->rcon_password])
            ->andFilterWhere(['like', 'map', $this->map])
            ->andFilterWhere(['like', 'db_host', $this->db_host])
            ->andFilterWhere(['like', 'db_name', $this->db_name])
            ->andFilterWhere(['like', 'db_user', $this->db_user])
            ->andFilterWhere(['like', 'db_password', $this->db_password])
            ->andFilterWhere(['like', 'tag', $this->tag])
            ->andFilterWhere(['like', 'commands', $this->commands])
            ->andFilterWhere(['like', 'discord_token', $this->discord_token]);

        return $dataProvider;
    }
}

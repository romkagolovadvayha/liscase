<?php

namespace backend\models;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\user\User;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Query;

/**
 * Поиск кланов в админке.
 */
class ClanSearch extends Clan
{
    /** @var string|null фильтр по нику лидера */
    public $leader_username;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'server_id', 'leader_user_id'], 'integer'],
            [['name', 'tag', 'privacy', 'leader_username'], 'safe'],
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
        $activeMembersCountSub = (new Query())
            ->select('COUNT(*)')
            ->from(['am' => ClanMember::tableName()])
            ->where('am.clan_id = c.id')
            ->andWhere(['IS', 'am.leave_date', null]);

        $query = Clan::find()
            ->alias('c')
            ->with(['server', 'leaderUser'])
            ->select(['c.*', 'activeMembersCount' => $activeMembersCountSub]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
                'attributes' => [
                    'id',
                    'name',
                    'tag',
                    'server_id',
                    'created_at',
                    'level',
                ],
            ],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['c.id' => $this->id]);
        $query->andFilterWhere(['like', 'c.name', $this->name]);
        $query->andFilterWhere(['like', 'c.tag', $this->tag]);
        $query->andFilterWhere(['c.server_id' => $this->server_id]);
        $query->andFilterWhere(['c.privacy' => $this->privacy]);

        if ($this->leader_username !== null && $this->leader_username !== '') {
            $query->andWhere([
                'in',
                'c.leader_user_id',
                User::find()->select('id')->where(['like', 'username', $this->leader_username]),
            ]);
        }

        return $dataProvider;
    }
}

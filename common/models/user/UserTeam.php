<?php

namespace common\models\user;

use common\models\servers\Servers;
use common\models\team\Team;
use common\models\teams\Teams;
use Yii;

/**
 * This is the model class for table "user_team".
 *
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int $server_id
 * @property string|null $wipe
 *
 * @property Servers $server
 * @property Team $team
 * @property User $user
 */
class UserTeam extends \yii\db\ActiveRecord
{
    public $leader_id = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_team';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'team_id', 'server_id'], 'required'],
            [['user_id', 'team_id', 'server_id'], 'integer'],
            [['wipe'], 'string', 'max' => 255],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['team_id'], 'exist', 'skipOnError' => true, 'targetClass' => Team::class, 'targetAttribute' => ['team_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'team_id' => 'Team ID',
            'server_id' => 'Server ID',
            'wipe' => 'Wipe',
        ];
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Gets query for [[Team]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'team_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @param $serverId
     * @param $userId
     * @param $wipe
     *
     * @return array|UserTeam[]
     */
    public static function getTeamList($serverId, $userId, $wipe): array
    {
        $ut = UserTeam::tableName();
        $tt = Team::tableName();

        /** @var Teams[] $rows */
        // все участники команды пользователя на сервере/вайпе
        $rows = Teams::find()->alias('t')
                     ->innerJoin(Teams::tableName() . ' t2',
                                 't.leader_user_id = t2.leader_user_id AND t.server_id = t2.server_id AND t.wipe = t2.wipe'
                     )
                     ->where([
                                 't2.user_id'   => $userId,
                                 't2.server_id' => $serverId,
                                 't2.wipe'      => $wipe,
                             ])
                     ->select([
                                  't.id',
                                  't.leader_user_id',
                                  't.user_id',
                                  't.server_id',
                                  't.wipe',
                                  't.created_at',
                              ])
                     ->orderBy(['t.user_id' => SORT_ASC])
                     ->asArray()
                     ->all();

        if (!$rows) {
            return [];
        }
        $authorId = $rows[0]->leader_user_id;

        $users = [];
        foreach ($rows as $team) {
            if ($team->user_id != $authorId) {
                continue;
            }
            $usr = $team->user;
            if (!$usr) {
                continue;
            }
            $users[] = [
                'link'      => $usr->getLink('stats'),
                'username'  => $usr->username,
                'is_online'    => $usr->getStatus(),
                'date_visit' => $usr->last_visit_server_at,
                'time_visit' => strtotime($usr->last_visit_server_at),
                'avatar'    => $usr->getAvatar(),
                'is_leader' => true,
            ];
        }
        foreach ($rows as $team) {
            if ($team->user_id == $authorId) {
                continue;
            }
            $usr = $team->user;
            if (!$usr) {
                continue;
            }
            $users[] = [
                'link'      => $usr->getLink('stats'),
                'username'  => $usr->username,
                'is_online'    => $usr->getStatus(),
                'date_visit' => $usr->last_visit_server_at,
                'time_visit' => strtotime($usr->last_visit_server_at),
                'avatar'    => $usr->getAvatar(),
                'is_leader' => false,
            ];
        }

        return $users;
    }
}

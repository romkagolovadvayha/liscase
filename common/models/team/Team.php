<?php

namespace common\models\team;

use common\models\servers\Servers;
use common\models\statistics\Teams;
use common\models\user\User;
use common\models\user\UserTeam;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "team".
 *
 * @property int $id
 * @property int $team_author_id
 * @property int $server_id
 * @property string|null $wipe
 *
 * @property Servers $server
 * @property User $teamAuthor
 *
 * @property UserTeam[] $userTeams
 */
class Team extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'team';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['team_author_id', 'server_id'], 'required'],
            [['team_author_id', 'server_id'], 'integer'],
            [['wipe'], 'string', 'max' => 255],
            [['team_author_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['team_author_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'team_author_id' => 'Team Author ID',
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
     * Gets query for [[TeamAuthor]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTeamAuthor()
    {
        return $this->hasOne(User::class, ['id' => 'team_author_id']);
    }

    /**
     * Gets query for [[UserTeams]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserTeams()
    {
        return $this->hasMany(UserTeam::class, ['team_id' => 'id']);
    }

    /**
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function updateTeam($item, $server, $wipeDate) {
        switch ($item->type) {
            case 'invite_accepted':
                self::inviteAccepted($item, $server, $wipeDate);
                break;
            case 'leaved':
                self::leaved($item, $server, $wipeDate);
                break;
            case 'kicked':
                self::kicked($item, $server, $wipeDate);
                break;
            case 'disband':
                self::disbanded($item, $server, $wipeDate);
                break;
        }
    }

    /**
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function inviteAccepted($item, $server, $wipeDate) {
        $user = User::findBySteamId($item->steam_id, false, 'teams 6');
        $userTeam = UserTeam::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['server_id' => $server->id])
            ->andWhere(['wipe' => $wipeDate])
            ->one();

        if (!empty($userTeam)) {
            $userTeam->delete();
        }

        $author = User::findBySteamId($item->team_author, false, 'teams 7');
        /** @var UserTeam $authorTeam */
        $authorTeam = UserTeam::find()
                            ->andWhere(['user_id' => $author->id])
                            ->andWhere(['server_id' => $server->id])
                            ->andWhere(['wipe' => $wipeDate])
                            ->one();
        if (!empty($authorTeam)) {
            Team::addUserToTeam($authorTeam->team_id, $user->id, $server, $wipeDate);
        } else {
            Team::createTeam($user->id, $author->id, $server, $wipeDate);
        }
    }

    /**
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function leaved($item, $server, $wipeDate) {
        Team::removeUserAllTeams($item->id, $server, $wipeDate);
    }

    /**
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function kicked($item, $server, $wipeDate) {
        Team::removeUserAllTeams($item->id, $server, $wipeDate);
    }

    /**
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function disbanded($item, $server, $wipeDate) {
        Team::removeUserAllTeams($item->id, $server, $wipeDate);
    }

    public static function addUserToTeam($teamId, $userId, $server, $wipeDate) {
        Team::removeUserAllTeams($userId, $server, $wipeDate);
        $team = Team::findOne($teamId);
        $userTeam = new UserTeam();
        $userTeam->user_id = $userId;
        $userTeam->team_id = $teamId;
        $userTeam->server_id = $team->server_id;
        $userTeam->wipe = $team->wipe;
        $userTeam->save();
    }

    /**
     * @param string $userId
     * @param string $author_id
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function createTeam($userId, $author_id, $server, $wipeDate) {
        $team = new Team();
        $team->team_author_id = $author_id;
        $team->server_id = $server->id;
        $team->wipe = $wipeDate;

        if ($team->save()) {
            Team::addUserToTeam($team->id, $userId, $server, $wipeDate);
            Team::addUserToTeam($team->id, $author_id, $server, $wipeDate);
        }
    }

    public static function removeUserFromTeam($teamId, $userId) {
        $userTeam = UserTeam::find()
            ->andWhere(['team_id' => $teamId])
            ->andWhere(['user_id' => $userId])
            ->one();
        if (!empty($userTeam)) {
            $userTeam->delete();
        }
    }

    public static function removeUserAllTeams($userId, $server, $wipeDate) {
        $userTeam = UserTeam::find()
                            ->andWhere(['server_id' => $server->id])
                            ->andWhere(['wipe' => $wipeDate])
                            ->andWhere(['user_id' => $userId])
                            ->all();
        foreach ($userTeam as $item) {
            $item->delete();
        }
    }
}

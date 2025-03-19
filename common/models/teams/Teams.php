<?php

namespace common\models\teams;

use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "teams".
 *
 * @property int $id
 * @property int $leader_user_id
 * @property int $user_id
 * @property int $server_id
 * @property string|null $wipe
 * @property string|null $created_at
 *
 * @property Servers $server
 * @property User $leaderUser
 * @property User $user
 *
 */
class Teams extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'teams';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['leader_user_id', 'user_id', 'server_id'], 'required'],
            [['leader_user_id', 'user_id', 'server_id'], 'integer'],
            [['wipe'], 'string', 'max' => 255],
            [['leader_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['leader_user_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
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
            'leader_user_id' => 'Team Author ID',
            'user_id' => 'User ID',
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
     * Gets query for [[LeaderUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLeaderUser()
    {
        return $this->hasOne(User::class, ['id' => 'leader_user_id']);
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
     * @param Teams $item
     * @param Servers $server
     * @param string $wipeDate
     *
     * @return void
     */
    public static function updateTeam($leaderSteamId, $members, $serverId, $wipeDate) {
        /** @var User[] $users */
        $users = User::find()
            ->andWhere(['IN', 'steam_id', ArrayHelper::merge([$leaderSteamId], $members)])
            ->indexBy('steam_id')
            ->all();
        if (empty($users[$leaderSteamId])) {
            $users[$leaderSteamId] = User::findBySteamId($leaderSteamId);
        }
        Teams::removeUserLeaderAllTeams($users[$leaderSteamId]->id, $serverId, $wipeDate);
        Yii::$app->telegramChats->sendMessage(json_encode($members));
        foreach ($members as $steamId) {
            if (empty($users[$steamId])) {
                $users[$steamId] = User::findBySteamId($steamId);
            }
            Teams::removeUserLeaderAllTeams($users[$steamId]->id, $serverId, $wipeDate);
            Teams::leaveFromTeam($users[$steamId]->id, $serverId, $wipeDate);
        }
        foreach ($members as $steamId) {
            $model = new Teams();
            $model->leader_user_id = $users[$leaderSteamId]->id;
            $model->user_id = $users[$steamId]->id;
            $model->server_id = $serverId;
            $model->wipe = $wipeDate;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save();
        }
    }

    public static function leaveFromTeam($userId, $serverId, $wipeDate) {
        $teams = Teams::find()
                      ->andWhere(['user_id' => $userId])
                      ->andWhere(['server_id' => $serverId])
                      ->andWhere(['wipe' => $wipeDate])
                      ->all();
        foreach ($teams as $item) {
            $item->delete();
        }
    }

    public static function removeUserLeaderAllTeams($userId, $serverId, $wipeDate) {
        $teams = Teams::find()
                            ->andWhere(['leader_user_id' => $userId])
                            ->andWhere(['server_id' => $serverId])
                            ->andWhere(['wipe' => $wipeDate])
                            ->all();
        foreach ($teams as $item) {
            $item->delete();
        }
    }
}

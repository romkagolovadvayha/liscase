<?php

namespace common\models\clan;

use common\models\servers\Servers;
use Yii;

/**
 * This is the model class for table "user_clan_stats".
 *
 * @property int         $id
 * @property int|null    $user_clan_id
 * @property int|null    $clan_id
 * @property string|null $steam_id
 * @property string|null $key
 * @property int|null    $value
 * @property int|null    $server_id
 * @property string|null $wipe
 * @property string|null $updated_at
 * @property string|null $created_at
 *
 * @property Servers     $server
 * @property UserClan    $userClan
 */
class UserClanStats extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_clan_stats';
    }

    public function rules()
    {
        return [
            [['user_clan_id', 'value', 'server_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['key'], 'string', 'max' => 60],
            [['steam_id'], 'string', 'max' => 19],
            [['wipe'], 'string', 'max' => 30],

            // связи
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['user_clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserClan::class, 'targetAttribute' => ['user_clan_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'user_clan_id' => 'Клан',
            'steam_id'     => 'Steam ID',
            'key'          => 'Ключ статистики',
            'value'        => 'Значение',
            'server_id'    => 'Сервер',
            'wipe'         => 'Вайп',
            'updated_at'   => 'Дата обновления',
            'created_at'   => 'Дата создания',
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
     * Gets query for [[UserClan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserClan()
    {
        return $this->hasOne(UserClan::class, ['id' => 'user_clan_id']);
    }

    public static function getSum($serverId, $wipe, $clanId, $key) {
        return UserClanStats::find()
            ->andWhere(['server_id' => $serverId])
            ->andWhere(['wipe' => $wipe])
            ->andWhere(['clan_id' => $clanId])
            ->andWhere(['key' => $key])
            ->sum('value') ?? 0;
    }
}

<?php

namespace common\models\bans;

use common\models\servers\Servers;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "bans".
 *
 * @property int $id
 * @property int $username
 * @property int $user_id
 * @property string $steam_id
 * @property string|null $reason
 * @property string|null $banned_at
 * @property string|null $unbanned_at
 * @property string|null $ip
 * @property int|null $server_id
 *
 * @property Servers $server
 * @property User $user
 */
class Bans extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'bans';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'steam_id'], 'required'],
            [['user_id', 'server_id'], 'integer'],
            [['banned_at', 'unbanned_at'], 'safe'],
            [['steam_id'], 'string', 'max' => 19],
            [['reason', 'username'], 'string', 'max' => 255],
            [['ip'], 'string', 'max' => 120],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
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
            'user_id' => Yii::t('common', 'Пользователь'),
            'steam_id' => 'Steam ID',
            'reason' => Yii::t('common', 'Причина'),
            'banned_at' => Yii::t('common', 'Дата бана'),
            'unbanned_at' => Yii::t('common', 'Дата разбана'),
            'ip' => 'IP',
            'server_id' => Yii::t('common', 'Сервер'),
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // Инвалидируем кэш списка банов
        Yii::$app->cache->delete('api_banlist_base');
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        parent::afterDelete();
        
        // Инвалидируем кэш списка банов
        Yii::$app->cache->delete('api_banlist_base');
    }
}

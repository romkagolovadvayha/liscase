<?php

namespace common\models\serverskin;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "server_skin_like".
 *
 * @property int $id
 * @property int $server_skin_id
 * @property int $user_id
 * @property int $type
 * @property string|null $created_at
 *
 * @property ServerSkin $serverSkin
 * @property User $user
 */
class ServerSkinLike extends \yii\db\ActiveRecord
{
    public const TYPE_LIKE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'server_skin_like';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['server_skin_id', 'user_id', 'type'], 'required'],
            [['server_skin_id', 'user_id', 'type'], 'integer'],
            [['created_at'], 'safe'],
            [['server_skin_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServerSkin::class, 'targetAttribute' => ['server_skin_id' => 'id']],
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
            'server_skin_id' => 'Skin ID',
            'user_id' => 'User ID',
            'type' => 'Type',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[ServerSkin]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServerSkin()
    {
        return $this->hasOne(ServerSkin::class, ['id' => 'server_skin_id']);
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
}

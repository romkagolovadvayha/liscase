<?php

namespace common\models\support;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "support_read".
 *
 * @property int $user_id
 * @property int $support_message_id
 *
 * @property SupportMessage $supportMessage
 * @property User $user
 */
class SupportRead extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support_read';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'support_message_id'], 'required'],
            [['user_id', 'support_message_id'], 'integer'],
            [['support_message_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportMessage::class, 'targetAttribute' => ['support_message_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'user_id' => 'User ID',
            'support_message_id' => 'Support Message ID',
        ];
    }

    /**
     * Gets query for [[SupportMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupportMessage()
    {
        return $this->hasOne(SupportMessage::class, ['id' => 'support_message_id']);
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

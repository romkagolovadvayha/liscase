<?php

namespace common\models\support;

use common\models\auth\AuthAssignment;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "support_read".
 *
 * @property int  $user_id
 * @property int  $support_message_id
 * @property int  $support_id
 * @property bool $status
 *
 * @property SupportMessage $supportMessage
 * @property Support $support
 * @property User $user
 */
class SupportRead extends \yii\db\ActiveRecord
{
    const STATUS_UNREAD = 0;
    const STATUS_READED = 1;

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
            [['user_id', 'support_message_id', 'status', 'support_id'], 'integer'],
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
     * Gets query for [[Support]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupport()
    {
        return $this->hasOne(Support::class, ['id' => 'support_id']);
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

    public static function readedAll($supportId, $userId = null)
    {
        if (empty($userId)) {
            SupportRead::updateAll(['status' => SupportRead::STATUS_READED], "support_id = {$supportId} AND status = 0");
        }
        if (!empty($userId)) {
            SupportRead::updateAll(['status' => SupportRead::STATUS_READED], "user_id = {$userId} AND support_id = {$supportId} AND status = 0");
        }
    }

    public static function createRecord($ownerId, $userId, $messageId, $supportId)
    {
        if ($ownerId !== $userId) {
            $model = new SupportRead();
            $model->user_id = $ownerId;
            $model->support_message_id = $messageId;
            $model->support_id = $supportId;
            $model->status = SupportRead::STATUS_UNREAD;
            $model->save();
        }

        $admins = AuthAssignment::find()
            ->andWhere(['IN', 'item_name', ['ADMIN', 'MODERATOR']])
            ->all();
        foreach ($admins as $admin) {
            if (empty($admin->user)) {
                continue;
            }
            if ($admin->user->id === $ownerId) {
                continue;
            }
            if ($admin->user->id === $userId) {
                continue;
            }
            $model = new SupportRead();
            $model->user_id = $admin->user->id;
            $model->support_message_id = $messageId;
            $model->support_id = $supportId;
            $model->status = SupportRead::STATUS_UNREAD;
            $model->save();
        }
    }
}

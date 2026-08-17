<?php

namespace common\models\support;

use common\components\queue\support\SupportRconNotifyJob;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "support_message".
 *
 * @property int $id
 * @property int $support_id
 * @property int $user_id
 * @property string $message
 * @property string|null $created_at
 *
 * @property Support $support
 * @property SupportFile[] $supportFiles
 * @property SupportRead[] $supportReads
 * @property User $user
 */
class SupportMessage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_AFTER_INSERT, [$this, 'notifyTicketOwnerInGame']);
    }
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support_message';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['support_id'], 'required'],
            [['support_id', 'user_id'], 'integer'],
            [['message'], 'string'],
            [['created_at'], 'safe'],
            [['support_id'], 'exist', 'skipOnError' => true, 'targetClass' => Support::class, 'targetAttribute' => ['support_id' => 'id']],
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
            'support_id' => 'Support ID',
            'user_id' => 'User ID',
            'message' => 'Message',
            'created_at' => 'Created At',
        ];
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
     * Gets query for [[SupportFiles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupportFiles()
    {
        return $this->hasMany(SupportFile::class, ['support_message_id' => 'id']);
    }

    /**
     * Gets query for [[SupportReads]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupportReads()
    {
        return $this->hasMany(SupportRead::class, ['support_message_id' => 'id']);
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
     * Ставит в очередь игровое уведомление владельца тикета, чтобы RCON не блокировал save().
     * Окончательная проверка автора выполняется в задаче: уведомлять нужно о любом ответе
     * не от самого владельца, включая автоматического помощника.
     */
    public function notifyTicketOwnerInGame()
    {
        if (empty($this->user_id)) {
            return;
        }

        try {
            $queue = Yii::$app->get('queueProcess', false);
            if (!$queue) {
                return;
            }
            $queue->push(new SupportRconNotifyJob(['messageId' => $this->id]));
        } catch (\Throwable $e) {
            // Сохранённое сообщение важнее уведомления: недоступная очередь не должна ломать ответ в тикете.
            Yii::warning(
                'Failed to enqueue in-game support notification for message #' . $this->id . ': ' . $e->getMessage(),
                __METHOD__
            );
        }
    }
}

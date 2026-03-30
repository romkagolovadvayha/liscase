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

    /**
     * Помечает прочитанным и возвращает id сообщений, которые были непрочитанными.
     *
     * @param int      $supportId
     * @param int|null $userId    если null — все непрочитанные по тикету (как readedAll без userId)
     *
     * @return int[] support_message_id
     */
    public static function readedAllReturningMessageIds(int $supportId, ?int $userId = null): array
    {
        $query = static::find()
            ->select('support_message_id')
            ->where(['support_id' => $supportId, 'status' => self::STATUS_UNREAD]);
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }
        $ids = $query->column();
        static::readedAll($supportId, $userId);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * WebSocket: отправителям обновить индикатор прочтения по помеченным сообщениям.
     */
    public static function notifyReadReceiptsWebSocketIfNeeded(Support $ticket, int $readerUserId, array $markedMessageIds): void
    {
        if ($markedMessageIds === []) {
            return;
        }
        $readStates = [];
        $ticketOwnerId = (int) $ticket->user_id;
        foreach (array_unique($markedMessageIds) as $mid) {
            $msg = SupportMessage::findOne((int) $mid);
            if (!$msg || $msg->user_id === null) {
                continue;
            }
            $senderId = (int) $msg->user_id;
            $readStates[] = [
                'messageId' => (int) $mid,
                'senderUserId' => $senderId,
                'is_read' => self::isOutgoingRead((int) $mid, $senderId, $ticketOwnerId),
            ];
        }
        if ($readStates === []) {
            return;
        }
        try {
            \console\controllers\NotificationServer::broadcastSupportMessagesRead(
                (int) $ticket->getNumber(),
                $readerUserId,
                $readStates
            );
        } catch (\Throwable $e) {
            Yii::warning('notifyReadReceiptsWebSocketIfNeeded: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Прочтение исходящего сообщения адресатами (для индикатора «доставлено / прочитано»).
     * — Сообщение от владельца тикета: прочитано, если хотя бы одна запись получателя (персонал) со status READED.
     * — Сообщение от персонала: прочитано, если владелец тикета отметил сообщение прочитанным (его запись READED).
     */
    public static function isOutgoingRead(int $messageId, int $senderUserId, int $ticketOwnerId): bool
    {
        $rows = static::find()
            ->where(['support_message_id' => $messageId])
            ->andWhere(['!=', 'user_id', $senderUserId])
            ->all();

        if (empty($rows)) {
            return false;
        }

        if ((int) $senderUserId === (int) $ticketOwnerId) {
            foreach ($rows as $row) {
                if ((int) $row->status === static::STATUS_READED) {
                    return true;
                }
            }

            return false;
        }

        foreach ($rows as $row) {
            if ((int) $row->user_id === (int) $ticketOwnerId) {
                return (int) $row->status === static::STATUS_READED;
            }
        }

        foreach ($rows as $row) {
            if ((int) $row->status === static::STATUS_READED) {
                return true;
            }
        }

        return false;
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

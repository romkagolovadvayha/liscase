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
        $condition = [
            'support_id' => (int) $supportId,
            'status' => self::STATUS_UNREAD,
        ];
        if (!empty($userId)) {
            $condition['user_id'] = (int) $userId;
        }

        static::updateAll(['status' => self::STATUS_READED], $condition);
    }

    /**
     * Помечает прочитанным и возвращает id сообщений, которые были непрочитанными.
     *
     * @param int      $supportId
     * @param int|null $userId    если null — все непрочитанные по тикету (как readedAll без userId)
     *
     * @param int|null $returnLimit maximum number of ids returned for realtime receipts;
     *                              null preserves the unbounded website/API behavior
     *
     * @return int[] support_message_id
     */
    public static function readedAllReturningMessageIds(int $supportId, ?int $userId = null, ?int $returnLimit = null): array
    {
        $query = static::find()
            ->select('support_message_id')
            ->where(['support_id' => $supportId, 'status' => self::STATUS_UNREAD]);
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }
        if ($returnLimit !== null) {
            $query
                ->orderBy(['id' => SORT_DESC])
                ->limit(max(1, min($returnLimit, 1000)));
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
        $messageIds = array_values(array_unique(array_filter(array_map('intval', $markedMessageIds))));
        if ($messageIds === []) {
            return;
        }

        $messages = SupportMessage::find()
            ->select(['id', 'user_id'])
            ->where(['id' => $messageIds])
            ->indexBy('id')
            ->asArray()
            ->all();
        if ($messages === []) {
            return;
        }

        $rowsByMessage = [];
        $rows = static::find()
            ->select(['support_message_id', 'user_id', 'status'])
            ->where(['support_message_id' => array_keys($messages)])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $rowsByMessage[(int) $row['support_message_id']][] = $row;
        }

        $readStates = [];
        $ticketOwnerId = (int) $ticket->user_id;
        foreach ($messageIds as $mid) {
            $message = $messages[$mid] ?? null;
            if (!$message || $message['user_id'] === null) {
                continue;
            }
            $senderId = (int) $message['user_id'];
            $readStates[] = [
                'messageId' => (int) $mid,
                'senderUserId' => $senderId,
                'is_read' => self::isOutgoingReadFromRows(
                    $rowsByMessage[$mid] ?? [],
                    $senderId,
                    $ticketOwnerId
                ),
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
            ->asArray()
            ->all();

        return static::isOutgoingReadFromRows($rows, $senderUserId, $ticketOwnerId);
    }

    /**
     * Returns at most $limit most recent unread ticket ids for a user.
     * Badge consumers cap their display, so this keeps latency and memory O(limit)
     * even when support_read contains millions of historical rows.
     *
     * @return int[] support_id values; duplicates represent unread messages
     */
    public static function unreadSupportIdsCapped(int $userId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $ids = static::find()
            ->select('support_id')
            ->where(['user_id' => $userId, 'status' => self::STATUS_UNREAD])
            ->orderBy(['id' => SORT_DESC])
            ->limit($limit)
            ->column();

        return array_values(array_map('intval', $ids));
    }

    private static function isOutgoingReadFromRows(array $rows, int $senderUserId, int $ticketOwnerId): bool
    {
        $recipientRows = [];
        foreach ($rows as $row) {
            if ((int) $row['user_id'] !== $senderUserId) {
                $recipientRows[] = $row;
            }
        }

        if ($recipientRows === []) {
            return false;
        }

        if ((int) $senderUserId === (int) $ticketOwnerId) {
            foreach ($recipientRows as $row) {
                if ((int) $row['status'] === static::STATUS_READED) {
                    return true;
                }
            }

            return false;
        }

        foreach ($recipientRows as $row) {
            if ((int) $row['user_id'] === (int) $ticketOwnerId) {
                return (int) $row['status'] === static::STATUS_READED;
            }
        }

        foreach ($recipientRows as $row) {
            if ((int) $row['status'] === static::STATUS_READED) {
                return true;
            }
        }

        return false;
    }

    public static function createRecord($ownerId, $userId, $messageId, $supportId)
    {
        $ownerId = (int) $ownerId;
        $userId = (int) $userId;
        $messageId = (int) $messageId;
        $supportId = (int) $supportId;
        $recipientIds = [];

        if ($ownerId !== $userId) {
            $recipientIds[$ownerId] = true;
        }

        $staffCacheKey = 'support_read_staff_recipient_ids_v3';
        $staffIds = Yii::$app->cache->get($staffCacheKey);
        if (!is_array($staffIds)) {
            $staffIds = AuthAssignment::find()
                ->alias('assignment')
                ->select('assignment.user_id')
                ->distinct()
                ->innerJoin(['recipient_user' => User::tableName()], 'recipient_user.id = assignment.user_id')
                ->andWhere(['assignment.item_name' => ['ADMIN', 'MODERATOR']])
                ->column();
            $staffIds = array_values(array_unique(array_map('intval', $staffIds)));
            Yii::$app->cache->set($staffCacheKey, $staffIds, 60);
        }

        foreach ($staffIds as $staffId) {
            $staffId = (int) $staffId;
            if ($staffId !== $ownerId && $staffId !== $userId) {
                $recipientIds[$staffId] = true;
            }
        }

        if ($recipientIds === []) {
            return;
        }

        $rows = [];
        foreach (array_keys($recipientIds) as $recipientId) {
            $rows[] = [(int) $recipientId, $messageId, $supportId, self::STATUS_UNREAD];
        }
        Yii::$app->db->createCommand()
            ->batchInsert(static::tableName(), ['user_id', 'support_message_id', 'support_id', 'status'], $rows)
            ->execute();
    }
}

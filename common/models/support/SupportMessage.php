<?php

namespace common\models\support;

use common\components\helpers\Role;
use common\models\rcon\RconTasks;
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
        $this->on(self::EVENT_AFTER_INSERT, [$this, 'notifyPlayerIfModeratorReply']);
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
     * Отправляет RCON команду для уведомления игрока, если сообщение отправлено модератором/администратором
     * (не создателем тикета)
     */
    public function notifyPlayerIfModeratorReply()
    {
        // Пропускаем системные сообщения (без user_id)
        if (empty($this->user_id)) {
            return;
        }

        // Загружаем автора сообщения и тикет
        $messageAuthor = $this->user;
        $ticket = $this->support;

        if (!$messageAuthor || !$ticket) {
            return;
        }

        // Проверяем, что автор сообщения является модератором/администратором/саппортом
        if (!$messageAuthor->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            return;
        }

        // Проверяем, что автор сообщения не является создателем тикета
        if ($messageAuthor->id === $ticket->user_id) {
            return;
        }

        // Получаем создателя тикета
        $ticketOwner = $ticket->user;
        if (!$ticketOwner || empty($ticketOwner->steam_id)) {
            return;
        }

        // Получаем сервер из тикета
        $server = $ticket->server;
        if (!$server) {
            // Если сервер не указан в тикете, пытаемся получить из пользователя
            $server = $ticketOwner->server;
        }

        if (!$server) {
            Yii::warning("Cannot send RCON notification: server not found for ticket #{$ticket->getNumber()}");
            return;
        }

        // Формируем сообщение для уведомления
        $notificationMessage = "Ваш вопрос был рассмотрен, ответ готов";
        
        // Формируем RCON команду
        $steamId = $ticketOwner->steam_id;
        $rconCommand = "support.notify {$steamId} \"{$notificationMessage}\"";

        try {
            // Выполняем RCON команду на конкретном сервере
            RconTasks::execute($rconCommand, [$server->tag]);
            Yii::info("RCON notification sent: {$rconCommand} on server {$server->tag}");
        } catch (\Exception $e) {
            Yii::error("Failed to send RCON notification: " . $e->getMessage());
        }
    }
}

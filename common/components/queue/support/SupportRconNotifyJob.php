<?php

namespace common\components\queue\support;

use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\support\SupportMessage;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Выполняет RCON-уведомление игрока об ответе саппорта в фоне (не блокирует сохранение сообщения).
 */
class SupportRconNotifyJob extends BaseObject implements JobInterface
{
    /** @var int ID сообщения поддержки */
    public $messageId;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        $model = SupportMessage::findOne($this->messageId);
        if (!$model) {
            return;
        }
        if (empty($model->user_id)) {
            return;
        }

        $messageAuthor = $model->user;
        $ticket = $model->support;
        if (!$messageAuthor || !$ticket) {
            return;
        }
        if (!$messageAuthor->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            return;
        }
        if ($messageAuthor->id === $ticket->user_id) {
            return;
        }

        $ticketOwner = $ticket->user;
        if (!$ticketOwner || empty($ticketOwner->steam_id)) {
            return;
        }

        $server = $ticket->server;
        if (!$server) {
            $server = $ticketOwner->server;
        }
        if (!$server) {
            Yii::warning("Cannot send RCON notification: server not found for ticket #{$ticket->getNumber()}");
            return;
        }

        $notificationMessage = "Ваш вопрос был рассмотрен, ответ готов";
        $rconCommand = "support.notify {$ticketOwner->steam_id} \"{$notificationMessage}\"";

        try {
            RconTasks::execute($rconCommand, [$server->tag]);
            Yii::info("RCON notification sent: {$rconCommand} on server {$server->tag}");
        } catch (\Exception $e) {
            Yii::error("Failed to send RCON notification: " . $e->getMessage());
        }
    }
}

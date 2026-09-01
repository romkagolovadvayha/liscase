<?php

namespace common\components\support;

use common\components\helpers\Role;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use Yii;
use yii\helpers\HtmlPurifier;

/**
 * Сохраняет ответ сотрудника так же, как обычное сообщение из страницы тикета.
 */
final class SupportStaffReplyService
{
    public function findAuthorizedStaffByUserId(int $userId): ?User
    {
        if ($userId < 1) {
            return null;
        }

        $user = User::findOne($userId);

        return $this->isAuthorizedStaff($user) ? $user : null;
    }

    public function isAuthorizedStaff(?User $user): bool
    {
        if ($user === null || $user->isSupportWritingBlocked()) {
            return false;
        }

        return $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
    }

    public function saveReply(Support $ticket, User $operator, string $replyText): SupportMessage
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $safeMessage = htmlspecialchars(
                HtmlPurifier::process($replyText),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $supportMessage = new SupportMessage();
            $supportMessage->support_id = (int)$ticket->id;
            $supportMessage->user_id = (int)$operator->id;
            $supportMessage->message = $safeMessage;
            $supportMessage->created_at = date('Y-m-d H:i:s');
            if (!$supportMessage->save()) {
                throw new \RuntimeException(json_encode($supportMessage->errors, JSON_UNESCAPED_UNICODE));
            }

            $ticket->updated_at = date('Y-m-d H:i:s');
            if (!$ticket->save(false)) {
                throw new \RuntimeException('Failed to update support ticket timestamp.');
            }

            $markedRead = SupportRead::readedAllReturningMessageIds((int)$ticket->id, (int)$operator->id);
            SupportRead::createRecord(
                (int)$ticket->user_id,
                (int)$operator->id,
                (int)$supportMessage->id,
                (int)$ticket->id
            );

            $transaction->commit();
            try {
                SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int)$operator->id, $markedRead);
            } catch (\Throwable $e) {
                Yii::warning('Support messenger read receipt broadcast failed: ' . $e->getMessage(), __METHOD__);
            }

            return $supportMessage;
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    public function broadcastReply(Support $ticket, SupportMessage $supportMessage, User $operator): void
    {
        try {
            \console\controllers\NotificationServer::broadcastNewSupportMessage(
                $ticket->getNumber(),
                (int)$supportMessage->id,
                (int)$operator->id,
                (int)$ticket->user_id
            );
        } catch (\Throwable $e) {
            Yii::warning('Support messenger reply WebSocket broadcast failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}

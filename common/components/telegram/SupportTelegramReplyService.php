<?php

namespace common\components\telegram;

use common\components\helpers\Role;
use common\models\support\Support;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\User;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;

/**
 * Сценарий ответа на обращение прямо из Telegram-группы поддержки.
 */
class SupportTelegramReplyService
{
    private const CALLBACK_PREFIX = 'support-reply:';
    private const PENDING_CACHE_PREFIX = 'telegram_support_reply_pending_v1:';
    private const PENDING_TTL = 15 * 60;

    public static function callbackData(int $ticketNumber): string
    {
        return self::CALLBACK_PREFIX . $ticketNumber;
    }

    public static function ticketNumberFromCallback(string $callbackData): ?int
    {
        if (!preg_match('/^' . preg_quote(self::CALLBACK_PREFIX, '/') . '(\d{1,12})$/', $callbackData, $matches)) {
            return null;
        }

        $ticketNumber = (int)$matches[1];
        return $ticketNumber > Support::NUMBER_OFFSET ? $ticketNumber : null;
    }

    /**
     * @param int|string|null $chatId
     */
    public static function isSupportChat($chatId): bool
    {
        if ($chatId === null || $chatId === '') {
            return false;
        }

        $configuredChatId = (string)Yii::$app->settings->get('tgbotSupportAlert_chatId');
        return $configuredChatId !== '' && (string)$chatId === $configuredChatId;
    }

    /**
     * @param int|string $supportChatId
     * @param int|string|null $operatorTelegramId
     * @param string|null $operatorTelegramUsername
     */
    public function beginReply(
        $supportChatId,
        $operatorTelegramId,
        int $ticketNumber,
        ?string $operatorTelegramUsername = null
    ): array
    {
        if (!self::isSupportChat($supportChatId)) {
            return $this->message('⛔ Эта кнопка работает только в чате поддержки.');
        }

        $operator = $this->findStaffByTelegramId($operatorTelegramId);
        if ($operator === null) {
            return $this->message(
                '⛔ Ваш Telegram не привязан к аккаунту сотрудника поддержки или у аккаунта нет нужной роли.'
            );
        }

        $ticket = Support::findByNumber($ticketNumber);
        if ($ticket === null) {
            return $this->message('⛔ Тикет не найден. Возможно, он был удалён.');
        }
        if ((int)$ticket->status === Support::STATUS_CLOSED) {
            return $this->message('⛔ Тикет уже закрыт. Ответ отправить нельзя.');
        }

        Yii::$app->cache->set(
            $this->pendingCacheKey($operatorTelegramId),
            [
                'ticketNumber' => $ticketNumber,
                'supportChatId' => (string)$supportChatId,
            ],
            self::PENDING_TTL
        );

        $operatorName = Html::encode((string)$operator->username);
        $hasTelegramUsername = is_string($operatorTelegramUsername)
            && preg_match('/^[a-zA-Z0-9_]{5,32}$/', $operatorTelegramUsername);
        $operatorMention = $hasTelegramUsername
            ? '@' . $operatorTelegramUsername
            : '<b>' . $operatorName . '</b>';
        return [
            'forceReply' => true,
            'forceReplySelective' => (bool)$hasTelegramUsername,
            'callbackNotice' => 'Режим ответа включён',
            'message' => "✍️ {$operatorMention}, напишите ответ для тикета <b>#{$ticketNumber}</b>.\n"
                . 'Он сохранится в тикете как обычный ответ от вашего аккаунта. '
                . 'Если поле ввода не открылось, ответьте на это сообщение вручную. '
                . 'Для отмены отправьте <code>/cancel</code>.',
            'placeholder' => "Ответ для тикета #{$ticketNumber}",
        ];
    }

    /**
     * @param array $message Telegram message update из группы поддержки
     * @return array
     */
    public function handleMessage(array $message): array
    {
        $supportChatId = ArrayHelper::getValue($message, 'chat.id');
        $operatorTelegramId = ArrayHelper::getValue($message, 'from.id');
        if (!self::isSupportChat($supportChatId) || empty($operatorTelegramId)) {
            return [];
        }

        $cacheKey = $this->pendingCacheKey($operatorTelegramId);
        $pending = Yii::$app->cache->get($cacheKey);
        if (!is_array($pending) || empty($pending['ticketNumber'])) {
            return [];
        }
        if ((string)ArrayHelper::getValue($pending, 'supportChatId') !== (string)$supportChatId) {
            Yii::$app->cache->delete($cacheKey);
            return $this->message('⛔ Чат ответа не совпадает. Нажмите «Ответить» ещё раз.');
        }

        $replyText = trim((string)ArrayHelper::getValue($message, 'text', ''));
        if ($replyText === '/cancel') {
            Yii::$app->cache->delete($cacheKey);
            return $this->message('Отправка ответа отменена.');
        }
        if ($replyText === '') {
            return [
                'forceReply' => true,
                'message' => '⛔ Отправьте текстовый ответ или используйте <code>/cancel</code>.',
                'placeholder' => 'Введите текст ответа',
            ];
        }

        $operator = $this->findStaffByTelegramId($operatorTelegramId);
        if ($operator === null) {
            Yii::$app->cache->delete($cacheKey);
            return $this->message('⛔ Аккаунт сотрудника не найден или право ответа было отозвано.');
        }

        $ticketNumber = (int)$pending['ticketNumber'];
        $ticket = Support::findByNumber($ticketNumber);
        if ($ticket === null || (int)$ticket->status === Support::STATUS_CLOSED) {
            Yii::$app->cache->delete($cacheKey);
            return $this->message('⛔ Тикет не найден или уже закрыт. Ответ не отправлен.');
        }

        try {
            $supportMessage = $this->saveReply($ticket, $operator, $replyText);
        } catch (\Throwable $e) {
            Yii::error('Telegram support reply save failed: ' . $e->getMessage(), __METHOD__);
            return $this->message('⛔ Не удалось сохранить ответ. Попробуйте ещё раз.');
        }

        Yii::$app->cache->delete($cacheKey);
        $this->broadcastReply($ticket, $supportMessage, $operator);

        return $this->message(
            "✅ Ответ сотрудника <b>" . Html::encode((string)$operator->username)
            . "</b> сохранён в тикете <b>#{$ticketNumber}</b>."
        );
    }

    /**
     * @param int|string|null $telegramId
     */
    private function findStaffByTelegramId($telegramId): ?User
    {
        if (empty($telegramId)) {
            return null;
        }

        $user = User::findByChatId($telegramId);
        if ($user === null || $user->isSupportWritingBlocked()) {
            return null;
        }
        if (!$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            return null;
        }

        return $user;
    }

    private function saveReply(Support $ticket, User $operator, string $replyText): SupportMessage
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
            SupportRead::notifyReadReceiptsWebSocketIfNeeded($ticket, (int)$operator->id, $markedRead);

            return $supportMessage;
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }
    }

    private function broadcastReply(Support $ticket, SupportMessage $supportMessage, User $operator): void
    {
        try {
            \console\controllers\NotificationServer::broadcastNewSupportMessage(
                $ticket->getNumber(),
                (int)$supportMessage->id,
                (int)$operator->id,
                (int)$ticket->user_id
            );
        } catch (\Throwable $e) {
            Yii::warning('Telegram support reply WebSocket broadcast failed: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * @param int|string $operatorTelegramId
     */
    private function pendingCacheKey($operatorTelegramId): string
    {
        return self::PENDING_CACHE_PREFIX . preg_replace('/[^0-9-]/', '', (string)$operatorTelegramId);
    }

    private function message(string $text): array
    {
        return [
            'message' => $text,
            'buttons' => [],
        ];
    }
}

<?php

namespace common\components\max;

use common\components\support\SupportStaffReplyService;
use common\models\support\Support;
use common\models\user\User;
use Yii;

/**
 * Сценарий ответа на обращение прямо из MAX-чата поддержки.
 */
final class MaxSupportReplyService
{
    private const PENDING_CACHE_PREFIX = 'max_support_reply_pending_v1:';
    private const PENDING_TTL = 15 * 60;

    private MaxSupportSettings $settings;
    private SupportStaffReplyService $staffReplies;

    public function __construct()
    {
        $this->settings = new MaxSupportSettings();
        $this->staffReplies = new SupportStaffReplyService();
    }

    /**
     * @param int|string|null $chatId
     */
    public function isSupportChat($chatId): bool
    {
        if ($chatId === null || $chatId === '') {
            return false;
        }

        $configuredChatId = $this->settings->chatId();

        return $configuredChatId !== '' && (string)$chatId === $configuredChatId;
    }

    /**
     * @param int|string|null $supportChatId
     * @param int|string|null $operatorMaxId
     */
    public function beginReply($supportChatId, $operatorMaxId, int $ticketNumber): array
    {
        if (!$this->isSupportChat($supportChatId)) {
            return $this->message('⛔ Эта кнопка работает только в MAX-чате поддержки.');
        }

        $safeMaxId = preg_replace('/[^0-9]/', '', (string)$operatorMaxId);
        $operator = $this->findStaffByMaxId($operatorMaxId);
        if ($operator === null) {
            return $this->message(
                '⛔ Не найден доступный аккаунт для ответа. Проверьте привязку сотрудника '
                . 'или пользователя по умолчанию со Steam ID '
                . $this->settings->defaultOperatorSteamId() . '.'
                . ($safeMaxId !== '' ? "\nВаш MAX ID: {$safeMaxId}" : '')
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
            $this->pendingCacheKey($operatorMaxId),
            [
                'ticketNumber' => $ticketNumber,
                'supportChatId' => (string)$supportChatId,
            ],
            self::PENDING_TTL
        );

        return [
            'callbackNotice' => 'Режим ответа включён',
            'message' => "✍️ {$operator->username}, напишите ответ для тикета #{$ticketNumber}.\n"
                . 'Он сохранится в тикете как обычный ответ от вашего аккаунта. '
                . 'Для отмены отправьте /cancel.'
                . "\n\nВаш ID MAX: {$safeMaxId}",
        ];
    }

    /**
     * @param int|string|null $supportChatId
     * @param int|string|null $operatorMaxId
     */
    public function handleMessage($supportChatId, $operatorMaxId, string $replyText): array
    {
        if ($operatorMaxId === null || $operatorMaxId === '') {
            return [];
        }

        $cacheKey = $this->pendingCacheKey($operatorMaxId);
        $pending = Yii::$app->cache->get($cacheKey);
        if (!is_array($pending) || empty($pending['ticketNumber'])) {
            return [];
        }

        $pendingChatId = (string)($pending['supportChatId'] ?? '');
        if (!$this->isSupportChat($pendingChatId)) {
            Yii::$app->cache->delete($cacheKey);

            return $this->message('⛔ Чат поддержки изменился. Нажмите «Ответить» ещё раз.');
        }
        if ($supportChatId !== null && $supportChatId !== '' && (string)$supportChatId !== $pendingChatId) {
            return [];
        }

        $replyText = trim($replyText);
        if ($replyText === '/cancel') {
            Yii::$app->cache->delete($cacheKey);

            return $this->message('Отправка ответа отменена.', $pendingChatId);
        }
        if ($replyText === '') {
            return $this->message('⛔ Отправьте текстовый ответ или используйте /cancel.', $pendingChatId);
        }

        $operator = $this->findStaffByMaxId($operatorMaxId);
        if ($operator === null) {
            Yii::$app->cache->delete($cacheKey);

            return $this->message(
                '⛔ Аккаунт сотрудника не найден или право ответа было отозвано.',
                $pendingChatId
            );
        }

        $ticketNumber = (int)$pending['ticketNumber'];
        $ticket = Support::findByNumber($ticketNumber);
        if ($ticket === null || (int)$ticket->status === Support::STATUS_CLOSED) {
            Yii::$app->cache->delete($cacheKey);

            return $this->message('⛔ Тикет не найден или уже закрыт. Ответ не отправлен.', $pendingChatId);
        }

        try {
            $supportMessage = $this->staffReplies->saveReply($ticket, $operator, $replyText);
        } catch (\Throwable $e) {
            Yii::error('MAX support reply save failed: ' . $e->getMessage(), __METHOD__);

            return $this->message('⛔ Не удалось сохранить ответ. Попробуйте ещё раз.', $pendingChatId);
        }

        Yii::$app->cache->delete($cacheKey);
        $this->staffReplies->broadcastReply($ticket, $supportMessage, $operator);

        return $this->message(
            "✅ Ответ сотрудника {$operator->username} сохранён в тикете #{$ticketNumber}.",
            $pendingChatId
        );
    }

    /**
     * @param int|string|null $maxUserId
     */
    private function findStaffByMaxId($maxUserId): ?User
    {
        if ($maxUserId === null || $maxUserId === '') {
            return null;
        }

        $siteUserId = $this->settings->operatorUserId($maxUserId);

        if ($siteUserId !== null) {
            return $this->staffReplies->findAuthorizedStaffByUserId($siteUserId);
        }

        // Если персональной привязки MAX ID нет, отвечаем от системного пользователя.
        // Для него роль персонала не обязательна: доступ уже ограничен секретом webhook
        // и конкретным MAX-чатом поддержки.
        $fallback = User::find()
            ->andWhere(['steam_id' => $this->settings->defaultOperatorSteamId()])
            ->one();
        if ($fallback === null || $fallback->isSupportWritingBlocked()) {
            return null;
        }

        return $fallback;
    }

    /**
     * @param int|string $operatorMaxId
     */
    private function pendingCacheKey($operatorMaxId): string
    {
        return self::PENDING_CACHE_PREFIX . preg_replace('/[^0-9]/', '', (string)$operatorMaxId);
    }

    private function message(string $text, ?string $chatId = null): array
    {
        $result = ['message' => $text];
        if ($chatId !== null && $chatId !== '') {
            $result['chatId'] = $chatId;
        }

        return $result;
    }
}

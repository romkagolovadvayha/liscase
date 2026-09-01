<?php

namespace common\components\telegram;

use common\components\support\SupportReplyCallback;
use common\components\support\SupportStaffReplyService;
use common\models\support\Support;
use common\models\user\User;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Сценарий ответа на обращение прямо из Telegram-группы поддержки.
 */
class SupportTelegramReplyService
{
    private const PENDING_CACHE_PREFIX = 'telegram_support_reply_pending_v1:';
    private const PENDING_TTL = 15 * 60;

    public static function callbackData(int $ticketNumber): string
    {
        return SupportReplyCallback::build($ticketNumber);
    }

    public static function ticketNumberFromCallback(string $callbackData): ?int
    {
        return SupportReplyCallback::parse($callbackData);
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
            $replyService = new SupportStaffReplyService();
            $supportMessage = $replyService->saveReply($ticket, $operator, $replyText);
        } catch (\Throwable $e) {
            Yii::error('Telegram support reply save failed: ' . $e->getMessage(), __METHOD__);
            return $this->message('⛔ Не удалось сохранить ответ. Попробуйте ещё раз.');
        }

        Yii::$app->cache->delete($cacheKey);
        $replyService->broadcastReply($ticket, $supportMessage, $operator);

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
        $replyService = new SupportStaffReplyService();

        return $replyService->isAuthorizedStaff($user) ? $user : null;
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

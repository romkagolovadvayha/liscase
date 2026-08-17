<?php

namespace common\components\queue\support;

use common\models\rcon\RconTasks;
use common\models\support\SupportMessage;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Отправляет владельцу тикета новый ответ в игровой чат через Helper.
 */
class SupportRconNotifyJob extends BaseObject implements JobInterface
{
    // Bundled notice effect is always available on the client and does not depend on an asset scene.
    private const SOUND_PREFAB = 'assets/bundled/prefabs/fx/notice/item.select.fx.prefab';
    private const MAX_MESSAGE_LENGTH = 700;

    /** @var int ID сообщения поддержки */
    public $messageId;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        /** @var SupportMessage|null $model */
        $model = SupportMessage::find()
            ->with(['user', 'support.user.server', 'support.server', 'supportFiles'])
            ->andWhere(['id' => (int) $this->messageId])
            ->one();
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

        // Уведомляем о любом собеседнике (саппорт, администратор, бот и т. п.), но не о сообщениях самого владельца.
        if ((int) $messageAuthor->id === (int) $ticket->user_id) {
            return;
        }

        $ticketOwner = $ticket->user;
        if (!$ticketOwner || empty($ticketOwner->steam_id)) {
            return;
        }

        $serverTags = [];
        if ($ticketOwner->server && !empty($ticketOwner->server->tag)) {
            $serverTags[] = (string) $ticketOwner->server->tag;
        }
        if ($ticket->server && !empty($ticket->server->tag)) {
            $serverTags[] = (string) $ticket->server->tag;
        }
        $serverTags = array_values(array_unique($serverTags));

        if (empty($serverTags)) {
            Yii::warning("Cannot send RCON notification: server not found for ticket #{$ticket->getNumber()}");
            return;
        }

        $replyText = self::plainText((string) $model->message);
        if ($replyText === '') {
            $replyText = !empty($model->supportFiles) ? '[Вложение]' : '[Новое сообщение]';
        }

        $author = self::singleLine((string) $messageAuthor->username);
        if ($author === '') {
            $author = 'Поддержка';
        }

        $ticketNumber = $ticket->getNumber();
        $messageRu = "Ответ в тикете #{$ticketNumber} от {$author}:\n{$replyText}";
        $messageEn = "Reply in ticket #{$ticketNumber} from {$author}:\n{$replyText}";
        $steamId = preg_replace('/\D+/', '', (string) $ticketOwner->steam_id);
        if ($steamId === '') {
            return;
        }

        $rconCommand = sprintf(
            'helper message "%s" "%s" "%s" "%s"',
            self::escapeRconArgument($messageRu),
            self::escapeRconArgument($messageEn),
            self::SOUND_PREFAB,
            $steamId
        );

        try {
            RconTasks::execute($rconCommand, $serverTags);
            Yii::info(
                "In-game support reply sent for ticket #{$ticketNumber} on servers " . implode(', ', $serverTags),
                __METHOD__
            );
        } catch (\Throwable $e) {
            Yii::error('Failed to send in-game support reply: ' . $e->getMessage(), __METHOD__);
        }
    }

    private static function plainText(string $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $value);
        $value = preg_replace('/[\t ]+/', ' ', $value);
        $value = preg_replace('/\n{3,}/', "\n\n", (string) $value);
        $value = trim((string) $value);

        if (mb_strlen($value) > self::MAX_MESSAGE_LENGTH) {
            $value = rtrim(mb_substr($value, 0, self::MAX_MESSAGE_LENGTH - 1)) . '…';
        }

        return $value;
    }

    private static function singleLine(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private static function escapeRconArgument(string $value): string
    {
        // Не допускаем выхода текста тикета из quoted-аргумента консольной команды.
        $value = str_replace('\\', '/', $value);
        $value = str_replace('"', '”', $value);

        // Helper превращает литеральный \n обратно в перенос строки.
        return str_replace(["\r\n", "\r", "\n"], '\\n', $value);
    }
}

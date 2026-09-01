<?php

namespace common\components\queue\support;

use common\components\support\SupportReplyCallback;
use common\models\support\Support;
use common\models\support\SupportFile;
use common\models\support\SupportMessage;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class BeforeMessageJob extends BaseObject implements JobInterface
{
    public $chatId;
    public $userId;
    public $username;
    public $message;
    public $chatNumber;
    /** @var int|null ID SupportMessage — чтобы подтянуть вложения */
    public $messageId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $domain = Yii::$app->settings->get('site_domain');
            $text = "💬 Новое сообщение.";
            $text .= PHP_EOL . "Имя: {$this->username}";
            $message = str_replace(['<br>', '<br/>'], PHP_EOL, (string)$this->message);
            $hasAttachments = $this->hasAttachments();
            if (trim(strip_tags($message)) === '') {
                $message = $hasAttachments ? '[вложение]' : '[пустое сообщение]';
            }
            $text .= PHP_EOL . "Сообщение: " . $message;
            $ticketUrl = "https://{$domain}/support/ticket?id={$this->chatNumber}";
            $text .= PHP_EOL . "<a href=\"{$ticketUrl}\">Перейти к тикету</a>";

            $buttons = [];
            $maxButtons = [];
            $isTicketOwnerMessage = $this->isTicketOwnerMessage();
            if ($isTicketOwnerMessage) {
                $callbackPayload = SupportReplyCallback::build((int)$this->chatNumber);
                $buttons[] = [
                    'text' => '✍️ Ответить',
                    'callback_data' => $callbackPayload,
                ];
                $maxButtons[] = [
                    'text' => '✍️ Ответить',
                    'payload' => $callbackPayload,
                ];
            }

            $photoUrls = $this->collectPhotoUrls();
            $plainSupportMessage = mb_substr($this->plainText($message), 0, 3400);
            $maxMessage = "💬 Новое сообщение."
                . PHP_EOL . "Имя: {$this->plainText((string)$this->username)}"
                . PHP_EOL . 'Сообщение: ' . $plainSupportMessage;
            if ($hasAttachments && trim(strip_tags((string)$this->message)) !== '') {
                $maxMessage .= PHP_EOL . 'Вложения: доступны в тикете';
            }
            $maxMessage .= PHP_EOL . 'Тикет: ' . $ticketUrl;
        } catch (\Throwable $e) {
            $this->reportFailure('prepare', $e);

            return;
        }

        try {
            if (empty($photoUrls)) {
                Yii::$app->telegramSupport->sendMessage($text, $buttons);
            } else {
                // caption в Telegram — максимум 1024 символа
                $caption = mb_substr($text, 0, 1024);
                Yii::$app->telegramSupport->sendMessage($caption, $buttons, $photoUrls[0]);

                foreach (array_slice($photoUrls, 1) as $photoUrl) {
                    Yii::$app->telegramSupport->sendMessage('', [], $photoUrl);
                }
            }
        } catch (\Throwable $e) {
            $this->reportFailure('telegram', $e);
        }

        try {
            Yii::$app->maxSupportBot->sendSupportMessage($maxMessage, $maxButtons);
        } catch (\Throwable $e) {
            $this->reportFailure('max', $e);
        }
    }

    /**
     * @return string[]
     */
    private function collectPhotoUrls(): array
    {
        if (empty($this->messageId)) {
            return [];
        }

        /** @var SupportMessage|null $message */
        $message = SupportMessage::find()
            ->with('supportFiles')
            ->andWhere(['id' => (int)$this->messageId])
            ->one();

        if ($message === null) {
            return [];
        }

        $urls = [];
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

        foreach ($message->supportFiles as $file) {
            /** @var SupportFile $file */
            $mime = strtolower((string)$file->mimetype);
            $ext = strtolower(pathinfo((string)$file->file, PATHINFO_EXTENSION));
            $isImage = (strpos($mime, 'image/') === 0 && $mime !== 'image/svg+xml')
                || ($mime === '' && in_array($ext, $imageExt, true));

            if (!$isImage) {
                continue;
            }

            $url = $file->getPublicUrl();
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_slice($urls, 0, 10);
    }

    private function hasAttachments(): bool
    {
        if (empty($this->messageId)) {
            return false;
        }

        return SupportFile::find()
            ->andWhere(['support_message_id' => (int)$this->messageId])
            ->exists();
    }

    /**
     * Кнопка ответа нужна только у входящих сообщений владельца тикета.
     */
    private function isTicketOwnerMessage(): bool
    {
        if (empty($this->chatId) || empty($this->userId) || empty($this->chatNumber)) {
            return false;
        }

        return Support::find()
            ->andWhere([
                'id' => (int)$this->chatId,
                'user_id' => (int)$this->userId,
            ])
            ->exists();
    }

    private function plainText(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function reportFailure(string $channel, \Throwable $e): void
    {
        $message = "BeforeMessageJob ({$channel}): " . $e->getLine() . ':' . $e->getMessage();
        Yii::warning($message, __METHOD__);
        try {
            Yii::$app->telegramChats->sendMessage($message);
        } catch (\Throwable $ignored) {
            // Ошибка резервного оповещения не должна перезапускать задачу очереди.
        }
    }
}

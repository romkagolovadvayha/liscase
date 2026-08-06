<?php

namespace common\components\queue\support;

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
            if (trim(strip_tags($message)) === '') {
                $message = $this->hasAttachments() ? '[вложение]' : '[пустое сообщение]';
            }
            $text .= PHP_EOL . "Сообщение: " . $message;
            $text .= PHP_EOL . "<a href=\"https://{$domain}/support/ticket?id={$this->chatNumber}\">Перейти к тикету</a>";

            $photoUrls = $this->collectPhotoUrls();
            if (empty($photoUrls)) {
                Yii::$app->telegramSupport->sendMessage($text);
                return;
            }

            // caption в Telegram — максимум 1024 символа
            $caption = mb_substr($text, 0, 1024);
            Yii::$app->telegramSupport->sendMessage($caption, [], $photoUrls[0]);

            foreach (array_slice($photoUrls, 1) as $photoUrl) {
                Yii::$app->telegramSupport->sendMessage('', [], $photoUrl);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("BeforeMessageJob: " . $e->getLine() . ":" . $e->getMessage());
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
}

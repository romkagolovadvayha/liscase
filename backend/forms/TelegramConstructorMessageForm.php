<?php

namespace backend\forms;

use backend\models\TelegramConstructorButtons;
use backend\models\TelegramConstructorMessage;
use common\components\storage\S3Api;
use Yii;
use yii\base\BaseObject;
use yii\web\UploadedFile;

class TelegramConstructorMessageForm extends TelegramConstructorMessage
{
    private const S3_PREFIX = 'uploads/telegram/';

    /**
     * @var UploadedFile
     */
    public $image_file;
    public $image_url; // Ссылка на изображение (начинается с @)
    /** @var string способ добавления изображения: none, url или upload */
    public $image_mode;
    public $path_file;
    public $is_delete_image;
    public $buttons;
    public $buttonsTitles;
    /** @var string|array текст сообщения по языкам (message[ru-RU] и т.д.) */
    public $message;
    /** ID сообщения для кнопки в модалке — отдельно, чтобы не перезаписывать message[] в POST */
    public $buttonResponseMessageId;

    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'trim'],
            [['title'], 'string', 'max' => 190],
            [['buttons', 'is_delete_image', 'message', 'buttonsTitles', 'image_url', 'buttonResponseMessageId'], 'safe'],
            [['image_mode'], 'in', 'range' => ['none', 'url', 'upload']],
            [['message'], 'validateMessageContent'],
            [['buttons'], 'validateButtons'],
            [['image_file'],
             'file',
             'skipOnEmpty' => true,
             'extensions' => 'png, jpg, jpeg, gif, webp',
             'maxSize' => 1024 * 1024 * 3,
             'tooBig' => 'Изображение больше 3 МБ. Выберите файл меньшего размера.',],
        ];
    }

    public function validateMessageContent($attribute): void
    {
        $messages = is_array($this->message) ? $this->message : ['ru-RU' => (string)$this->message];
        $hasText = false;
        foreach ($messages as $message) {
            if (trim(strip_tags((string)$message)) !== '') {
                $hasText = true;
            }
            if ($this->getVisibleTextLength((string)$message) > 4096) {
                $this->addError($attribute, 'Текст сообщения длиннее 4096 символов — Telegram не сможет его отправить.');
            }
        }

        $imageUrls = is_array($this->image_url) ? $this->image_url : [];
        $hasUrl = (bool)array_filter($imageUrls, static fn($url) => trim((string)$url) !== '');
        $deleteFlags = is_array($this->is_delete_image) ? $this->is_delete_image : [];
        $existingImageLink = !$this->isNewRecord ? (string)$this->getImageLink('ru-RU') : '';
        $hasExistingImage = empty($deleteFlags['ru-RU']) && $existingImageLink !== '';
        $hasExistingUploadImage = $hasExistingImage && strpos($existingImageLink, '@') !== 0;
        $hasUpload = UploadedFile::getInstance($this, 'image_file[ru-RU]') !== null;

        if ($this->image_mode === 'url' && !$hasUrl) {
            $this->addError('image_url', 'Вставьте ссылку на изображение или выберите другой вариант.');
        }
        if ($this->image_mode === 'upload' && !$hasUpload && !$hasExistingUploadImage) {
            $this->addError('image_file', 'Выберите изображение или другой вариант.');
        }

        foreach ($imageUrls as $url) {
            $url = trim((string)$url);
            if ($url === '') {
                continue;
            }
            $validatedUrl = str_replace('{user_id}', '1', ltrim($url, '@'));
            $scheme = strtolower((string)parse_url($validatedUrl, PHP_URL_SCHEME));
            if (!filter_var($validatedUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                $this->addError('image_url', 'Ссылка на изображение должна начинаться с http:// или https://.');
            }
            if (mb_strlen($url) > 254) {
                $this->addError('image_url', 'Ссылка на изображение слишком длинная (максимум 254 символа).');
            }
        }

        if (!$hasText && !$hasUrl && !$hasExistingImage && !$hasUpload) {
            $this->addError($attribute, 'Добавьте текст или изображение сообщения.');
        }
    }

    public function beforeValidate(): bool
    {
        if ($this->image_mode === 'none') {
            $this->is_delete_image = ['ru-RU' => 1];
            $this->image_url = ['ru-RU' => ''];
        }

        return parent::beforeValidate();
    }

    public function validateButtons($attribute): void
    {
        if (!is_array($this->buttons)) {
            return;
        }

        foreach ($this->buttons as $index => $button) {
            $title = trim((string)($button['title']['ru-RU'] ?? ''));
            $url = trim((string)($button['url'] ?? ''));
            $messageId = (int)($button['message'] ?? 0);
            if ($title === '') {
                $this->addError($attribute, 'У каждой кнопки должно быть название.');
            }
            if (mb_strlen($title) > 64) {
                $this->addError($attribute, 'Название кнопки должно быть не длиннее 64 символов.');
            }
            if (($url === '') === ($messageId === 0)) {
                $this->addError($attribute, 'У кнопки должно быть ровно одно действие: ссылка или ответное сообщение.');
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true))) {
                $this->addError($attribute, 'Проверьте ссылку у кнопки «' . ($title ?: ($index + 1)) . '».');
            }
            if (mb_strlen($url) > 255) {
                $this->addError($attribute, 'Ссылка у кнопки «' . ($title ?: ($index + 1)) . '» длиннее 255 символов.');
            }
            if ($messageId > 0 && !TelegramConstructorMessage::find()->andWhere(['id' => $messageId])->exists()) {
                $this->addError($attribute, 'Ответный шаблон у кнопки «' . ($title ?: ($index + 1)) . '» не найден.');
            }
        }
    }

    private function getVisibleTextLength(string $html): int
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p\s*>/i', "\n", (string)$text);
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_strlen(trim($text));
    }

    /**
     * @return bool
     */
    public function saveRecord()
    {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        $uploadedImages = [];
        $imagesToDelete = [];

        try {
            if (!$this->save(false)) {
                throw new \RuntimeException('Не удалось сохранить запись шаблона.');
            }

            // message/image_url не входят в атрибуты таблицы — берём из POST по formName()
            $formName = $this->formName();
            $postForm = Yii::$app->request->post($formName, []);
            $rawMessage = $postForm['message'] ?? $this->message;
            $defaultLang = 'ru-RU';
            $messageByLang = [];
            if (is_array($rawMessage)) {
                $messageByLang = $rawMessage;
            } elseif ($rawMessage !== null && $rawMessage !== '') {
                $messageByLang = [$defaultLang => (string)$rawMessage];
            }
            $imageUrlByLang = is_array($this->image_url) ? $this->image_url : [];
            $isDeleteByLang = is_array($this->is_delete_image) ? $this->is_delete_image : [];

            $imageFileArray = is_array($this->image_file) ? $this->image_file : [];
            $languages = array_unique(array_filter(array_merge(
                array_keys($messageByLang),
                array_keys($imageUrlByLang),
                array_keys($imageFileArray)
            )));
            if (empty($languages)) {
                $languages = [$defaultLang];
                $messageByLang[$defaultLang] = '';
            }

            foreach ($languages as $language) {
                $messageText = trim((string)($messageByLang[$language] ?? ''));
                $oldImageLink = (string)$this->getImageLink($language);
                $imageUrl = trim((string)($imageUrlByLang[$language] ?? ''));

                if ($imageUrl !== '') {
                    if (strpos($imageUrl, '@') !== 0) {
                        $imageUrl = '@' . $imageUrl;
                    }
                    if ($oldImageLink !== '' && $oldImageLink !== $imageUrl) {
                        $imagesToDelete[] = $oldImageLink;
                    }
                    $this->updateLanguage($language, $messageText, $imageUrl);
                    continue;
                }

                $imageFile = UploadedFile::getInstance($this, "image_file[$language]");
                if ($imageFile) {
                    $fileName = $this->id . '_' . uniqid('', true) . '.' . strtolower($imageFile->extension);
                    $s3Key = self::S3_PREFIX . $fileName;
                    $contentType = $imageFile->type ?: ('image/' . strtolower($imageFile->extension));
                    $uploadedS3Key = $this->uploadImageToS3($s3Key, $imageFile->tempName, $contentType);
                    if ($uploadedS3Key === false) {
                        throw new \RuntimeException('Не удалось загрузить изображение.');
                    }
                    $uploadedImages[] = $uploadedS3Key;
                    if ($oldImageLink !== '' && $oldImageLink !== $uploadedS3Key) {
                        $imagesToDelete[] = $oldImageLink;
                    }
                    $this->updateLanguage($language, $messageText, $uploadedS3Key);
                } elseif ($oldImageLink !== '' && !empty($isDeleteByLang[$language])) {
                    $imagesToDelete[] = $oldImageLink;
                    $this->updateLanguage($language, $messageText, null);
                } else {
                    $this->updateLanguage($language, $messageText, null, false);
                }
            }

            TelegramConstructorButtons::deleteAll(['telegram_constructor_message_id' => $this->id]);
            if (!empty($this->buttons)) {
                foreach ($this->buttons as $item) {
                    $button                                  = new TelegramConstructorButtons();
                    $button->telegram_constructor_message_id = $this->id;
                    if (!empty($item['url'])) {
                        $button->url = $item['url'];
                    }
                    if (!empty($item['message'])) {
                        $button->callback_telegram_constructor_message_id = $item['message'];
                    }
                    $button->created_at = date('Y-m-d H:i:s');
                    if (!$button->save(false)) {
                        throw new \RuntimeException('Не удалось сохранить кнопку сообщения.');
                    }
                    foreach ($item['title'] as $language => $title) {
                        $button->updateLanguage($language, $title);
                    }
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            foreach ($uploadedImages as $uploadedImage) {
                $this->deleteImageFromS3IfNeeded($uploadedImage);
            }
            Yii::error('Telegram message save failed: ' . $e->getMessage(), __METHOD__);
            $this->addError('image_file', 'Не удалось сохранить шаблон. Повторите попытку.');
            return false;
        }

        foreach (array_unique($imagesToDelete) as $imageToDelete) {
            $this->deleteImageFromS3IfNeeded($imageToDelete);
        }

        return true;
    }

    /**
     * @param string $s3Key
     * @param string $tempPath
     * @param string|null $contentType
     * @return string|false
     */
    private function uploadImageToS3(string $s3Key, string $tempPath, ?string $contentType = null)
    {
        if (!Yii::$app->has('s3Api')) {
            Yii::warning('s3Api component is not configured', __METHOD__);
            return false;
        }

        /** @var S3Api $s3Api */
        $s3Api = Yii::$app->s3Api;
        return $s3Api->putFile($s3Key, $tempPath, $contentType);
    }

    /**
     * @param string|null $imageLink
     * @return void
     */
    private function deleteImageFromS3IfNeeded(?string $imageLink): void
    {
        if (empty($imageLink) || strpos($imageLink, '@') === 0 || !Yii::$app->has('s3Api')) {
            return;
        }

        $s3Key = $this->extractS3Key($imageLink);
        if ($s3Key === null) {
            return;
        }

        /** @var S3Api $s3Api */
        $s3Api = Yii::$app->s3Api;
        $s3Api->deleteFile($s3Key);
    }

    /**
     * @param string $imageLink
     * @return string|null
     */
    private function extractS3Key(string $imageLink): ?string
    {
        $imageLink = trim($imageLink);
        if ($imageLink === '') {
            return null;
        }

        if (strpos($imageLink, self::S3_PREFIX) === 0) {
            return $imageLink;
        }

        if (preg_match('#/uploads/telegram/(.+)$#i', $imageLink, $matches)) {
            return self::S3_PREFIX . ltrim($matches[1], '/');
        }

        return null;
    }
}

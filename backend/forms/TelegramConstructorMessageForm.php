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
            [['message'], 'validateMessageContent'],
            [['buttons'], 'validateButtons'],
            [['image_file'],
             'file',
             'skipOnEmpty' => true,
             'extensions' => 'png, jpg, jpeg, gif, webp',
             'maxSize' => 1024 * 1024 * 3,
             'tooBig' => 'The file was larger than 3 MB. Please upload a smaller file.',],
        ];
    }

    public function validateMessageContent($attribute): void
    {
        $messages = is_array($this->message) ? $this->message : ['ru-RU' => (string)$this->message];
        $hasText = false;
        foreach ($messages as $message) {
            if (trim(strip_tags((string)$message)) !== '') {
                $hasText = true;
                break;
            }
        }

        $imageUrls = is_array($this->image_url) ? $this->image_url : [];
        $hasUrl = (bool)array_filter($imageUrls, static fn($url) => trim((string)$url) !== '');
        $deleteFlags = is_array($this->is_delete_image) ? $this->is_delete_image : [];
        $hasExistingImage = !$this->isNewRecord
            && empty($deleteFlags['ru-RU'])
            && (bool)$this->getImageLink('ru-RU');
        $hasUpload = UploadedFile::getInstance($this, 'image_file[ru-RU]') !== null;

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
        }

        if (!$hasText && !$hasUrl && !$hasExistingImage && !$hasUpload) {
            $this->addError($attribute, 'Добавьте текст или изображение сообщения.');
        }
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
            if (($url === '') === ($messageId === 0)) {
                $this->addError($attribute, 'У кнопки должно быть ровно одно действие: ссылка или ответное сообщение.');
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true))) {
                $this->addError($attribute, 'Проверьте ссылку у кнопки «' . ($title ?: ($index + 1)) . '».');
            }
            if ($messageId > 0 && !TelegramConstructorMessage::find()->andWhere(['id' => $messageId])->exists()) {
                $this->addError($attribute, 'Ответный шаблон у кнопки «' . ($title ?: ($index + 1)) . '» не найден.');
            }
        }
    }

    /**
     * @return bool
     */
    public function saveRecord()
    {
        if (!$this->validate()) {
            return false;
        }

        $result = $this->save(false);

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();;
        }

        $updated = [];

        // message/image_url не входят в атрибуты таблицы — берём из POST по formName()
        $formName = $this->formName();
        $postForm = Yii::$app->request->post($formName, []);
        $rawMessage = $postForm['message'] ?? null;
        $defaultLang = 'ru-RU';
        $messageByLang = [];
        if (is_array($rawMessage)) {
            $messageByLang = $rawMessage;
        } elseif ($rawMessage !== null && $rawMessage !== '') {
            $messageByLang = [$defaultLang => (string)$rawMessage];
        }
        $imageUrlByLang = is_array($postForm['image_url'] ?? null) ? $postForm['image_url'] : [];
        $isDeleteByLang = is_array($postForm['is_delete_image'] ?? null) ? $postForm['is_delete_image'] : [];
        
        $imageFileArray = is_array($this->image_file) ? $this->image_file : [];
        $languages = array_merge(
            array_keys($messageByLang),
            array_keys($imageUrlByLang),
            array_keys($imageFileArray)
        );
        $languages = array_unique(array_filter($languages));
        // Если из POST ничего не подтянулось — сохраняем хотя бы для одного языка (как в форме)
        if (empty($languages)) {
            $languages = [$defaultLang];
            $msg = $postForm['message'] ?? null;
            $messageByLang[$defaultLang] = is_array($msg) ? ($msg[$defaultLang] ?? '') : (string)($msg ?? '');
        }
        
        foreach ($languages as $language) {
            $messageText = trim($messageByLang[$language] ?? '');
            
            $imageUrl = $imageUrlByLang[$language] ?? '';
            if (!empty($imageUrl)) {
                if (strpos($imageUrl, '@') !== 0) {
                    $imageUrl = '@' . $imageUrl;
                }
                $updated[$language] = true;
                $this->updateLanguage($language, $messageText, $imageUrl);
                continue;
            }
            
            // Если ссылка не указана, проверяем загрузку файла
            $imageFile = UploadedFile::getInstance($this, "image_file[$language]");
            if($imageFile) {
                $fileName = $this->id . '_' . uniqid('', true) . '.' . strtolower($imageFile->extension);
                $s3Key = self::S3_PREFIX . $fileName;
                $contentType = $imageFile->type ?: ('image/' . strtolower($imageFile->extension));
                $uploadedS3Key = $this->uploadImageToS3($s3Key, $imageFile->tempName, $contentType);
                if ($uploadedS3Key !== false) {
                    $oldImageLink = $this->getImageLink($language);
                    $this->deleteImageFromS3IfNeeded($oldImageLink);
                    $updated[$language] = true;
                    $this->updateLanguage($language, $messageText, $uploadedS3Key);
                } else {
                    Yii::warning("Telegram message image upload failed for language={$language}, messageId={$this->id}", __METHOD__);
                }
            } else if (!empty($this->getImageLink($language)) && !empty($isDeleteByLang[$language])) {
                $oldImageLink = $this->getImageLink($language);
                $this->deleteImageFromS3IfNeeded($oldImageLink);
                $updated[$language] = true;
                $this->updateLanguage($language, $messageText, null);
            } else {
                $updated[$language] = true;
                $this->updateLanguage($language, $messageText, null, false);
            }
        }

        if ($result) {
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
                    $button->save(false);
                    foreach ($item['title'] as $language => $title) {
                        $button->updateLanguage($language, $title);
                    }
                }
            }
        }

        return $result;
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

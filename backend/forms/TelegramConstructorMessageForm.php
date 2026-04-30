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
            [['buttons', 'title', 'is_delete_image', 'message', 'buttonsTitles', 'image_url', 'buttonResponseMessageId'], 'safe'],
            [['image_file'],
             'file',
             'skipOnEmpty' => true,
             'extensions' => 'png, jpg, jpeg, gif',
             'maxSize' => 1024 * 1024 * 3,
             'tooBig' => 'The file was larger than 3 MB. Please upload a smaller file.',],
        ];
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

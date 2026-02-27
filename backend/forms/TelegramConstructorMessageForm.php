<?php

namespace backend\forms;

use backend\models\TelegramConstructorButtons;
use backend\models\TelegramConstructorMessage;
use Yii;
use yii\base\BaseObject;
use yii\web\UploadedFile;

class TelegramConstructorMessageForm extends TelegramConstructorMessage
{

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
        $viewPath = Yii::getAlias('@app/web/uploads') . '/telegram';

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
                $fileName = $this->id . '_' . uniqid() . '.' . $imageFile->extension;
                if (file_exists($viewPath) && is_dir($viewPath)) {
                    try {
                        // если поменяем файл, то сохраним с другим названием, а прежний удалим
                        $fileLink = $viewPath . '/' . $fileName;
                        $oldImageLink = $this->getImageLink($language);
                        if(!empty($oldImageLink) && strpos($oldImageLink, '@') !== 0) {
                            // Удаляем старый файл только если это был файл, а не ссылка
                            if (file_exists($oldImageLink)) {
                                unlink($oldImageLink);
                            }
                        }
                        $imageFile->saveAs($fileLink);
                        if(!file_exists($fileLink)) {
                            \Yii::info("image file $fileLink was not created, maybe check sever configuration ", 'problem');
                        }
                    } catch (\Exception $e) {
                        \Yii::info("image file $fileLink was not created " . print_r($e->getMessage(), 1), 'problem');
                    }
                    $updated[$language] = true;
                    $this->updateLanguage($language, $messageText, $fileLink);
                }
            } else if (!empty($this->getImageLink($language)) && !empty($isDeleteByLang[$language])) {
                $oldImageLink = $this->getImageLink($language);
                if (strpos($oldImageLink, '@') !== 0 && file_exists($oldImageLink)) {
                    unlink($oldImageLink);
                }
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
}

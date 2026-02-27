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
    public $message;

    public function rules()
    {
        return [
            [['buttons', 'title', 'is_delete_image', 'message', 'buttonsTitles', 'image_url'], 'safe'],
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

        // message не входит в атрибуты таблицы, load() его не подставляет — берём из POST
        $postForm = Yii::$app->request->post('TelegramConstructorMessageForm', []);
        $rawMessage = $postForm['message'] ?? $this->message;
        $defaultLang = Yii::$app->language ?: 'ru';
        $messageByLang = is_array($rawMessage) ? $rawMessage : (($rawMessage !== null && $rawMessage !== '') ? [$defaultLang => (string)$rawMessage] : []);
        $imageUrlByLang = is_array($this->image_url) ? $this->image_url : [];
        $isDeleteByLang = is_array($this->is_delete_image) ? $this->is_delete_image : [];
        
        // Языки: всегда включаем ключи из message, плюс из image_file/image_url
        $imageFileArray = is_array($this->image_file) ? $this->image_file : [];
        $imageUrlArray = is_array($this->image_url) ? $this->image_url : [];
        $languages = array_merge(
            array_keys($messageByLang),
            array_keys($imageFileArray),
            array_keys($imageUrlArray)
        );
        $languages = array_unique(array_filter($languages));
        
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

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
    public $path_file;
    public $is_delete_image;
    public $buttons;
    public $buttonsTitles;
    public $message;

    public function rules()
    {
        return [
            [['buttons', 'title', 'is_delete_image', 'message', 'buttonsTitles'], 'safe'],
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
        foreach ($this->image_file as $language => $item) {
            $this->message[$language] = trim($this->message[$language]);
            $imageFile = UploadedFile::getInstance($this, "image_file[$language]");
            if($imageFile) {
                $fileName = $this->id . '_' . uniqid() . '.' . $imageFile->extension;
                if (file_exists($viewPath) && is_dir($viewPath)) {
                    try {
                        // если поменяем файл, то сохраним с другим названием, а прежний удалим
                        $fileLink = $viewPath . '/' . $fileName;
                        if(!empty($this->getImageLink($language))) {
                            if (file_exists($this->getImageLink($language))) {
                                unlink($this->getImageLink($language));
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
                    $this->updateLanguage($language, $this->message[$language], $fileLink);
                }
            } else if (!empty($this->getImageLink($language)) && $this->is_delete_image[$language]) {
                if (file_exists($this->getImageLink($language))) {
                    unlink($this->getImageLink($language));
                }
                $updated[$language] = true;
                $this->updateLanguage($language, $this->message[$language], null);
            } else {
                $updated[$language] = true;
                $this->updateLanguage($language, $this->message[$language], null, false);
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

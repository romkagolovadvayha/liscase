<?php

namespace backend\models;

use Yii;

/**
 * This is the model class for table "telegram_constructor_message".
 *
 * @property int $id
 * @property string $title
 * @property string|null $created_at
 * @property array $buttons
 *
 * @property TelegramConstructorButtons[] $telegramConstructorButtons
 * @property string $message
 * @property string|null $image_link
 */
class TelegramConstructorMessage extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor_message';
    }

    public function getMessage($language = 'ru-RU') {
        /** @var TelegramConstructorMessageLanguage $message */
        $message = TelegramConstructorMessageLanguage::find()
                                                     ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                     ->andWhere(['language' => $language])
                                                     ->one();
        return !empty($message) ? $message->message : '';
    }

    public function getImageLink($language = 'ru-RU') {
        /** @var TelegramConstructorMessageLanguage $message */
        $message = TelegramConstructorMessageLanguage::find()
                                                     ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                     ->andWhere(['language' => $language])
                                                     ->one();
        return !empty($message) ? $message->image_link : '';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string'],
            [['created_at'], 'safe'],
            [['image_link'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название',
            'image_link' => 'Ссылка на изображение',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Gets query for [[TelegramConstructorButtons]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTelegramConstructorButtons()
    {
        return $this->hasMany(TelegramConstructorButtons::className(), ['telegram_constructor_message_id' => 'id']);
    }

    /**
     * @inheritDoc
     */
    public function beforeSave($insert): bool
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    /**
     * @return array
     */
    public static function getList() {
        $result = [];
        /** @var TelegramConstructorMessage[] $messages */
        $messages = self::find()->all();
        foreach ($messages as $message) {
            $result[$message->id] = $message->title;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getPubUrl($baseUrl = '', $language = 'ru-Ru'): string
    {
        if(!$baseUrl) {
            $baseUrl = Yii::$app->params['baseUrl'];
        }
        return $baseUrl . '/uploads/telegram' . str_replace(Yii::getAlias('@app/web/uploads') . '/telegram', '', $this->getImageLink($language));
    }

    public function updateLanguage($language, $message = null, $imageLink = null, $updateImageLink = true)
    {
        /** @var TelegramConstructorMessageLanguage $message */
        $model = TelegramConstructorMessageLanguage::find()
                                                   ->andWhere(['telegram_constructor_message_id' => $this->id])
                                                   ->andWhere(['language' => $language])
                                                   ->one();
        if (empty($model)) {
            $model                                  = new TelegramConstructorMessageLanguage();
            $model->telegram_constructor_message_id = $this->id;
            $model->language                        = $language;
        }
        $model->message = $message;
        if ($updateImageLink) {
            $model->image_link = $imageLink;
        }
        $model->save(false);
    }

    public function getTelegramMessage($language, $photo = true) {
        $message = $this->getMessage($language);
        $message = str_replace(["<p>&nbsp;</p>", "<p>", "</p>", "<br>", "&nbsp;"], ["\n", "", "", "\n", ""], $this->makeStringUTF8($message));
        if ($this->getImageLink($language) && $photo) {
            $imageLink = $this->getPubUrl('', $language);
            $message = '<a href="' . $imageLink . '">&#8205;</a>' . $message;
        }

        return trim($message);
    }

    public function getTelegramButtons($language) {
        $buttons = [];
        foreach ($this->telegramConstructorButtons as $button) {
            $item = [
                'text' => $button->getText($language),
            ];

            if (!empty($button->callback_telegram_constructor_message_id)) {
                $data = [
                    'messageId' => $button->callback_telegram_constructor_message_id,
                    'current_language' => $language,
                ];
                $item['callback_data'] = json_encode($data);
            }

            if (!empty($button->url)) {
                $item['url'] = $button->url;
            }

            $buttons[] = $item;
        }

        return $buttons;
    }

    /**
     * @param $data
     * @return array|false|string|string[]|null
     */
    private function makeStringUTF8($data)
    {
        if (is_string($data) === true)
        {
            // has html entities?
            if (strpos($data, '&') !== false)
            {
                // if so, revert back to normal
                $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
            }
            // make sure it UTF-8
            if (function_exists('iconv') === true)
            {
                return @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            }
            if (function_exists('mb_convert_encoding') === true)
            {
                return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            return utf8_encode(utf8_decode($data));
        }
        return $data;
    }

}

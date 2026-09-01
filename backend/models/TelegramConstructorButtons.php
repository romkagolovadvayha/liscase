<?php

namespace backend\models;

use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "telegram_constructor_buttons".
 *
 * @property int $id
 * @property int $telegram_constructor_message_id
 * @property string|null $url
 * @property int|null $callback_telegram_constructor_message_id
 * @property string|null $created_at
 *
 * @property TelegramConstructorMessage $callbackTelegramConstructorMessage
 * @property TelegramConstructorMessage $telegramConstructorMessage
 * @property string $text
 */
class TelegramConstructorButtons extends \yii\db\ActiveRecord
{

    public $text;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor_buttons';
    }

    public function getText($language = 'ru-RU') {
        /** @var TelegramConstructorButtonsLanguage $model */
        $model = TelegramConstructorButtonsLanguage::find()
                                                     ->andWhere(['telegram_constructor_buttons_language_id' => $this->id])
                                                     ->andWhere(['language' => $language])
                                                     ->one();
        return !empty($model) ? $model->text : '';
    }

    public function getButonsText() {
        /** @var TelegramConstructorButtonsLanguage[] $models */
        $models = TelegramConstructorButtonsLanguage::find()
                                                     ->andWhere(['telegram_constructor_buttons_language_id' => $this->id])
                                                     ->all();

        $result = [];
        foreach ($models as $model) {
            $result[] = [
                'text' => $model->text,
                'language' => $model->language
            ];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['telegram_constructor_message_id'], 'required'],
            [['telegram_constructor_message_id', 'callback_telegram_constructor_message_id'], 'integer'],
            [['created_at'], 'safe'],
            [['url'], 'string', 'max' => 255],
            [['callback_telegram_constructor_message_id'], 'exist', 'skipOnError' => true, 'targetClass' => TelegramConstructorMessage::className(), 'targetAttribute' => ['callback_telegram_constructor_message_id' => 'id']],
            [['telegram_constructor_message_id'], 'exist', 'skipOnError' => true, 'targetClass' => TelegramConstructorMessage::className(), 'targetAttribute' => ['telegram_constructor_message_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'telegram_constructor_message_id' => 'ID сообщения',
            'url' => 'Ссылка',
            'callback_telegram_constructor_message_id' => 'Возвращаемое сообщение',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Gets query for [[CallbackTelegramConstructorMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCallbackTelegramConstructorMessage()
    {
        return $this->hasOne(TelegramConstructorMessage::className(), ['id' => 'callback_telegram_constructor_message_id']);
    }

    /**
     * Gets query for [[TelegramConstructorMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTelegramConstructorMessage()
    {
        return $this->hasOne(TelegramConstructorMessage::className(), ['id' => 'telegram_constructor_message_id']);
    }

    public function updateLanguage($language, $text = null)
    {
        /** @var TelegramConstructorButtonsLanguage $model */
        $model = TelegramConstructorButtonsLanguage::find()
                                                   ->andWhere(['telegram_constructor_buttons_language_id' => $this->id])
                                                   ->andWhere(['language' => $language])
                                                   ->one();
        if (empty($model)) {
            $model = new TelegramConstructorButtonsLanguage();
            $model->telegram_constructor_buttons_language_id = $this->id;
            $model->language                        = $language;
        }
        $model->text = $text;
        $model->save(false);
    }
}

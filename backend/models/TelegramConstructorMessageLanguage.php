<?php

namespace backend\models;

use Yii;

/**
 * This is the model class for table "telegram_constructor_message_language".
 *
 * @property int $id
 * @property int $telegram_constructor_message_id
 * @property string|null $language
 * @property string|null $image_link
 * @property string|null $message
 */
class TelegramConstructorMessageLanguage extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor_message_language';
    }

}

<?php

namespace backend\models;

use Yii;

/**
 * This is the model class for table "telegram_constructor_buttons_language".
 *
 * @property int $id
 * @property int $telegram_constructor_buttons_language_id
 * @property string|null $language
 * @property string|null $text
 */
class TelegramConstructorButtonsLanguage extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor_buttons_language';
    }

}

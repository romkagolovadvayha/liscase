<?php

namespace common\models\telegram;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "telegram_user".
 *
 * @property int    $id
 * @property string $name
 * @property string $username
 * @property int    $chat_id
 * @property int    $type
 * @property string $created_at
 *
 */
class TelegramUser extends \common\components\base\ActiveRecord
{

    const TYPE_RUSTOTEKA = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_user';
    }

    public static function createModel($name, $chatId, $username, $type)
    {
        $model = self::findOne(['chat_id' => $chatId, 'type' => $type]);
        if (!empty($model)) {
            return true;
        }

        $model = new self();
        $model->name = $name;
        $model->chat_id = $chatId;
        $model->username = $username;
        $model->type = $type;
        $model->created_at = date('Y-m-d H:i:s');

        return $model->save();
    }
}

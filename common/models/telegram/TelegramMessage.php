<?php

namespace common\models\telegram;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "telegram_message".
 *
 * @property int    $id
 * @property int    $chat_id
 * @property string $message
 * @property string $created_at
 *
 */
class TelegramMessage extends \common\components\base\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_message';
    }

    /**
     * @param $chatId
     * @param $message
     *
     * @return bool
     */
    public static function createModel($chatId, $message)
    {
        $model = new self();
        $model->chat_id = $chatId;
        $model->message = $message;
        $model->created_at = date('Y-m-d H:i:s');

        return $model->save();
    }
}

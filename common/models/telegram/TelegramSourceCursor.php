<?php
namespace common\models\telegram;

use yii\db\ActiveRecord;

class TelegramSourceCursor extends ActiveRecord
{
    public static function tableName() { return '{{%telegram_source_cursor}}'; }
    public function rules()
    {
        return [
            [['source','last_message_id','updated_at'], 'required'],
            [['last_message_id','updated_at'],'integer'],
            [['source'],'string','max'=>128],
        ];
    }
}

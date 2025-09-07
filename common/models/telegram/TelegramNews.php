<?php
namespace common\models\telegram;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $source_chat_id
 * @property int $source_message_id
 * @property string|null $media_group_id
 * @property string|null $content_type  // text|photo|video|document|other
 * @property string|null $text
 * @property string|null $caption
 * @property string|null $processed_text
 * @property string|null $processed_caption
 * @property string|null $target_chat_id
 * @property int $status               // 0=new, 1=published, 2=failed
 * @property int|null $published_message_id
 * @property string|null $error
 * @property string $raw_json
 * @property int $created_at
 * @property int $updated_at
 */
class TelegramNews extends ActiveRecord
{
    const STATUS_NEW = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_FAILED = 2;

    public static function tableName()
    {
        return '{{%telegram_news}}';
    }

    public function rules()
    {
        return [
            [['source_chat_id', 'source_message_id', 'raw_json', 'created_at', 'updated_at'], 'required'],
            [['source_message_id', 'status', 'published_message_id', 'created_at', 'updated_at'], 'integer'],
            [['text', 'caption', 'processed_text', 'processed_caption', 'error', 'raw_json'], 'string'],
            [['source_chat_id', 'media_group_id', 'content_type', 'target_chat_id'], 'string', 'max' => 64],
        ];
    }
}

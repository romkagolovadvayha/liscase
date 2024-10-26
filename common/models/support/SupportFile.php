<?php

namespace common\models\support;

use Yii;

/**
 * This is the model class for table "support_file".
 *
 * @property int $id
 * @property int $support_message_id
 * @property string $file
 * @property string|null $created_at
 *
 * @property SupportMessage $supportMessage
 */
class SupportFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['support_message_id', 'file'], 'required'],
            [['support_message_id'], 'integer'],
            [['created_at'], 'safe'],
            [['file'], 'string', 'max' => 512],
            [['support_message_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportMessage::class, 'targetAttribute' => ['support_message_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'support_message_id' => 'Support Message ID',
            'file' => 'File',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[SupportMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupportMessage()
    {
        return $this->hasOne(SupportMessage::class, ['id' => 'support_message_id']);
    }
}

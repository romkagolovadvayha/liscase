<?php

namespace common\models\tasks;

use Yii;

/**
 * This is the model class for table "tasks_tags".
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $color_hex
 * @property int|null $order_index
 * @property string|null $created_at
 *
 * @property TasksTagsAppointments[] $tasksTagsAppointments
 */
class TasksTags extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks_tags';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_index'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'color_hex'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'color_hex' => 'Color Hex',
            'order_index' => 'Order Index',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[TasksTagsAppointments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTasksTagsAppointments()
    {
        return $this->hasMany(TasksTagsAppointments::className(), ['tag_id' => 'id']);
    }
}

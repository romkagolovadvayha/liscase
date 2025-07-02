<?php

namespace common\models\tasks;

use Yii;

/**
 * This is the model class for table "tasks_tags_appointments".
 *
 * @property int $id
 * @property int|null $task_id
 * @property int|null $tag_id
 * @property string|null $created_at
 *
 * @property TasksTags $tag
 * @property Tasks $task
 */
class TasksTagsAppointments extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks_tags_appointments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['task_id', 'tag_id'], 'integer'],
            [['created_at'], 'safe'],
            [['tag_id'], 'exist', 'skipOnError' => true, 'targetClass' => TasksTags::className(), 'targetAttribute' => ['tag_id' => 'id']],
            [['task_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tasks::className(), 'targetAttribute' => ['task_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'task_id' => 'Task ID',
            'tag_id' => 'Tag ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Tag]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTag()
    {
        return $this->hasOne(TasksTags::className(), ['id' => 'tag_id']);
    }

    /**
     * Gets query for [[Task]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTask()
    {
        return $this->hasOne(Tasks::className(), ['id' => 'task_id']);
    }
}

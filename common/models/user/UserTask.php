<?php

namespace common\models\user;

use common\models\tasks\Task;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "user_task".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $task_id
 * @property string      $created_at
 *
 * @property User        $user
 * @property Task $task
 */
class UserTask extends \common\components\base\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_task';
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTask()
    {
        return $this->hasOne(Task::class, ['id' => 'task_id']);
    }

    /**
     * @param $userId
     * @param $taskId
     *
     * @return bool
     */
    public static function createRecord($userId, $taskId)
    {
        $model = new UserTask();
        $model->user_id = $userId;
        $model->task_id = $taskId;
        $model->created_at = date('Y-m-d H:i:s');
        return $model->save(false);
    }

    /**
     * @param $userId
     * @param $taskType
     *
     * @return Task|\yii\db\ActiveRecord|null
     */
    public static function getByType($userId, $taskType)
    {
        $task = Task::find()
                                  ->andWhere(['type' => $taskType])
                                  ->one();
        return UserTask::find()
                              ->andWhere(['user_id' => $userId])
                              ->andWhere(['task_id' => $task->id])
                              ->one();
    }
}

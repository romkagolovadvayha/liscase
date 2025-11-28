<?php

namespace common\models\tasks_v2;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "task_v2_user_completion".
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property int $count_completed
 * @property string|null $last_completed
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property TaskV2 $task
 * @property User $user
 */
class TaskV2UserCompletion extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'task_v2_user_completion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['task_id', 'user_id'], 'required'],
            [['task_id', 'user_id', 'count_completed'], 'integer'],
            [['last_completed', 'created_at', 'updated_at'], 'safe'],
            [['task_id', 'user_id'], 'unique', 'targetAttribute' => ['task_id', 'user_id']],
            [['task_id'], 'exist', 'skipOnError' => true, 'targetClass' => TaskV2::class, 'targetAttribute' => ['task_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'task_id' => Yii::t('common', 'ID задания'),
            'user_id' => Yii::t('common', 'ID пользователя'),
            'count_completed' => Yii::t('common', 'Количество выполнений'),
            'last_completed' => Yii::t('common', 'Последнее выполнение'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            $this->updated_at = date('Y-m-d H:i:s');
            $this->last_completed = date('Y-m-d H:i:s');
            return true;
        }
        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTask()
    {
        return $this->hasOne(TaskV2::class, ['id' => 'task_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Увеличить счетчик выполнений
     */
    public function incrementCount()
    {
        $this->count_completed++;
        $this->last_completed = date('Y-m-d H:i:s');
        return $this->save(false);
    }

    /**
     * Создать или обновить запись о выполнении
     * @param int $taskId
     * @param int $userId
     * @return TaskV2UserCompletion
     */
    public static function createOrUpdate($taskId, $userId)
    {
        $completion = static::find()
            ->where(['task_id' => $taskId, 'user_id' => $userId])
            ->one();

        if (!$completion) {
            $completion = new static();
            $completion->task_id = $taskId;
            $completion->user_id = $userId;
            $completion->count_completed = 0;
        }

        $completion->incrementCount();
        return $completion;
    }
}














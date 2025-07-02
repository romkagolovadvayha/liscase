<?php

namespace common\models\tasks;

use common\components\tasksProject\ProjectComponent;
use common\components\tasksPublishPlace\TasksPublishPlaceComponent;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "tasks_publish_place".
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $description
 * @property int|null $order_index
 * @property string|null $system_check_code
 * @property string|null $created_at
 *
 * @property Tasks[] $tasks
 */
class TasksPublishPlace extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks_publish_place';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_index'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'description'], 'string', 'max' => 255],
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
            'order_index' => 'Order Index',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Tasks]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTasks()
    {
        return $this->hasMany(Tasks::className(), ['tasks_publish_place_id' => 'id']);
    }

    /**
     * @param TasksProjects[] $projects
     * @param $tasks
     * @param $userTasks
     * @param $statuses
     * @param User $user
     * @param $showAll
     *
     * @return bool
     */
    public function hasVisibility($projects, $tasks, $userTasks, $statuses, $user, $showAll): bool
    {
        $component = TasksPublishPlaceComponent::getInstance($this->id);
        if ($showAll && !$component->visability($user)) {
            return false;
        }

        foreach ($projects as $item) {
            if ($item->hasVisibility($this->id, $tasks, $userTasks, $statuses, $user, $showAll)) {
                return true;
            }
        }
        return false;
    }
}

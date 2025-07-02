<?php

namespace common\models\tasks;

use common\components\tasks\TaskComponent;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "tasks_projects".
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $icon
 * @property bool $is_visibility_name
 * @property int|null $order_index
 * @property string|null $system_check_code
 * @property string|null $created_at
 *
 * @property Tasks[] $tasks
 */
class TasksProjects extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tasks_projects';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_index', 'is_visibility_name'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'icon'], 'string', 'max' => 255],
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
            'icon' => 'Icon',
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
        return $this->hasMany(Tasks::className(), ['tasks_projects_id' => 'id']);
    }

    /**
     * @param int   $publishPlaceId
     * @param Tasks[] $tasks
     * @param       $userTasks
     * @param       $statuses
     * @param User  $user
     * @param       $showAll
     *
     * @return bool
     */
    public function hasVisibility(int $publishPlaceId, array $tasks, $userTasks, $statuses, $user, $showAll): bool
    {
        foreach ($tasks as $item) {
           if ($item->hasVisibility($publishPlaceId, $this->id, $userTasks, $statuses, $user, $showAll)) {
               return true;
           }
        }
        return false;
    }
}

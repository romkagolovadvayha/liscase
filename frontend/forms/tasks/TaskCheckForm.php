<?php

namespace frontend\forms\tasks;

use common\components\tasks\TaskComponent;
use common\models\tasks\Tasks;
use common\models\tasks\TasksProjects;
use common\models\tasks\TasksPublishPlace;
use common\models\user\User;
use common\models\user\UserTasks;
use Yii;
use yii\helpers\ArrayHelper;

class TaskCheckForm extends Tasks
{
    public $result;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['result'], 'string', 'max' => 255],
        ];
    }

    /**
     * @return UserTasks|null
     * @throws \Exception
     */
    public function check(): ?UserTasks
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $checkComponent = TaskComponent::getInstance($this->system_check_code);

        /** @var UserTasks $userTask */
        $userTaskQuery = UserTasks::find()
            ->andWhere(['task_id' => $this->id])
            ->andWhere(['user_id' => $user->id]);

        if ($this->is_permanent) {
            $userTaskQuery->andWhere(['NOT IN', 'status', [UserTasks::STATUS_SUCCESS]]);
        }

        $userTask = $userTaskQuery->orderBy(['id' => SORT_DESC])->one();

        if (!$this->validate()) {
            return $userTask;
        }

        if ($this->is_email_field) {
            if (!empty($userTask) && empty($userTask->result)) {
                if (empty($this->result)) {
                    $this->addError('result', \Yii::t('common', 'Введите E-mail адрес'));
                    return $userTask;
                }
                $userTask->result = $this->result;
                $userTask->save();
            }
        }

        if (!empty($userTask) && $userTask->status === UserTasks::STATUS_GET_PROFIT) {
            $userTask = $checkComponent->profit($this->id, $user->id);
        } elseif ((empty($userTask) || in_array($userTask->status, [UserTasks::STATUS_CREATED, UserTasks::STATUS_REJECTED])) || $this->is_permanent) {
            $userTask = $checkComponent->check($this->id, $user->id);
        }

        return $userTask;
    }

}

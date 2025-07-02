<?php

namespace common\components\tasks;

use cabinet\forms\tasks\TaskCheckForm;
use common\models\profit\Profit;
use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;

abstract class BaseClass implements BaseInterface
{
    public function visability($user, $task) {
        return true;
    }

    public function check($taskId, $userId): UserTasks
    {
        return $this->updateUserTaskStatus($taskId, $userId, UserTasks::STATUS_WAITING);
    }

    public function profit($taskId, $userId, $amount = null, $profitType = null, $profitBalance = null): UserTasks
    {
        $task = Tasks::findOne($taskId);
        /** @var UserTasks $userTask */
        $userTaskQuery = UserTasks::find()
                                  ->andWhere(['task_id' => $taskId])
                                  ->andWhere(['user_id' => $userId]);

        if ($task->is_permanent) {
            $userTaskQuery->andWhere(['NOT IN', 'status', [UserTasks::STATUS_SUCCESS]]);
        }

        $userTask = $userTaskQuery->orderBy(['id' => SORT_DESC])->one();

        $amount = $task->amount;
        if (!empty($userTask->amount) && $userTask->amount >= $task->amount) {
            $amount = $userTask->amount;
        }

        $userTask->finished_at = date('Y-m-d H:i:s');
        $userTask->status = UserTasks::STATUS_SUCCESS;
        $userTask->amount = $amount;
        $userTask->awarded = true;
        if ($userTask->save(false)) {
            if (empty($profitType)) {
                $profitType = Profit::TYPE_TASK_DONE;
            }
            if (empty($profitBalance)) {
                $profitBalance = $userTask->user->getDigiuBalance();
            }
            $profit = new Profit();
            $profit->user_balance_id = $profitBalance->id;
            $profit->type            = $profitType;
            $profit->amount          = $amount;
            $profit->comment         = Yii::t('database', $task->name, [], $userTask->user->current_language);
            $profit->status          = 1;
            $profit->save();
            $profitBalance->recalculateBalance();
        }

        return $userTask;
    }

    /**
     * Принимать автоматически задание через это время
     */
    public function autoSuccessTime() {
        return null;
    }

    /**
     * Отклонять автоматически задание через это время
     */
    public function autoRejectTime() {
        return 60 * 60 * 24;
    }

    protected function updateUserTaskStatus($taskId, $userId, $status): UserTasks
    {
        $task = Tasks::findOne($taskId);
        /** @var UserTasks $userTask */
        $userTaskQuery = UserTasks::find()
                                  ->andWhere(['task_id' => $taskId])
                                  ->andWhere(['user_id' => $userId]);

        if ($task->is_permanent) {
            $userTaskQuery->andWhere(['NOT IN', 'status', [UserTasks::STATUS_SUCCESS]]);
        }

        $userTask = $userTaskQuery->orderBy(['id' => SORT_DESC])->one();

        if (empty($userTask)) {
            $userTask = new UserTasks();
            $userTask->task_id = $taskId;
            $userTask->user_id = $userId;
            $userTask->created_at = date('Y-m-d H:i:s');
        }
        if ($status === UserTasks::STATUS_WAITING) {
            $userTask->created_at = date('Y-m-d H:i:s');
        }
        $userTask->status = $status;
        $userTask->save(false);

        return $userTask;
    }

    public function getRedirectSuccess() {
        return null;
    }

}

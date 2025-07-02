<?php

/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var $ifShowActiveCreditTask */
/** @var $ifShowRevokedCreditTask */
/** @var $ifRenderPartnerActivateTask */
/** @var TasksPublishPlace[] $publishPlace */
/** @var TasksProjects[] $projects */
/** @var Tasks[] $tasks */
/** @var UserTasks[] $userTasks */

use common\models\tasks\TasksPublishPlace;
use common\models\tasks\TasksProjects;
use common\models\tasks\Tasks;
use frontend\assets\PromotionAsset;
use common\models\user\UserTasks;
use common\models\tasks\TasksTagsAppointments;
use common\models\tasks\TasksTags;
use kartik\form\ActiveForm;

$this->title = Yii::t('common', 'Актуальные задания');
PromotionAsset::register($this);
$user = Yii::$app->user->identity;

/** @var TasksTagsAppointments[] $tasksTagsAppointmentsData */
$tasksTagsAppointmentsData = TasksTagsAppointments::find()->all();
$tasksTagsAppointments = [];
foreach ($tasksTagsAppointmentsData as $tasksTagsAppointment) {
    if (empty($tasksTagsAppointments[$tasksTagsAppointment->task_id])) {
        $tasksTagsAppointments[$tasksTagsAppointment->task_id] = [];
    }
    $tasksTagsAppointments[$tasksTagsAppointment->task_id][] = $tasksTagsAppointment->tag->id;
}
$userTasks = UserTasks::find()
    ->andWhere(['user_id' => $user->id])
    ->indexBy('task_id')
    ->orderBy(['id' => SORT_DESC])
    ->all();
$tasksTags = TasksTags::find()
    ->orderBy(['order_index' => SORT_ASC])
    ->all();
$publishPlace = TasksPublishPlace::find()
    ->cache(60)
    ->orderBy(['order_index' => SORT_ASC])
    ->all();
$projects = TasksProjects::find()
    ->cache(60)
    ->andWhere(['OR', ['NOT IN', 'system_check_code', ['big_game']], 'system_check_code IS NULL'])
    ->orderBy(['order_index' => SORT_ASC])
    ->all();
$tasks = \common\models\tasks\Tasks::find()
    ->cache(60)
    ->andWhere(['is_publish' => 1])
    ->orderBy(['order_index' => SORT_ASC])
    ->all();

/** @var TasksPublishPlace $publishPlaceBeginner */
$publishPlaceBeginner = null;
foreach ($publishPlace as $publishPlaceItem) {
    if ($publishPlaceItem->system_check_code == 'beginner_place') {
        $publishPlaceBeginner = $publishPlaceItem;
        break;
    }
}

$progress = 0;
if (!empty($publishPlaceBeginner)) {
    $countTaskBeginner = 0;
    $countTaskBeginnerCompleted = 0;
    foreach ($tasks as $task) {
        if ($task->tasks_publish_place_id != 1) {
            continue;
        }
        if(!empty($task->lk_lang)) {
            $langs = json_decode($task->lk_lang,1);
            if (!in_array($user->current_language, $langs)) {
                continue;
            }
        }
        $countTaskBeginner++;
        if (empty($userTasks[$task->id])) {
            continue;
        }
        $userTask = $userTasks[$task->id];
        if (!in_array($userTask->status, [UserTasks::STATUS_SUCCESS, UserTasks::STATUS_GET_PROFIT])) {
            continue;
        }
        $countTaskBeginnerCompleted++;
    }
    if ($countTaskBeginnerCompleted > 0) {
        $progress = ceil($countTaskBeginnerCompleted / $countTaskBeginner * 100);
    }
}

?>
<div class="row" style="margin-top: 20px;">
    <div class="col-md-12 col-xs-12">
        <div class="tasktab_content js-statusList">
            <?php foreach ($publishPlace as $item): ?>
                <?= $this->render('blocks/_item_block', [
                    'model' => $item,
                    'projects' => $projects,
                    'tasks' => $tasks,
                    'userTasks' => $userTasks,
                    'statuses' => [UserTasks::STATUS_CREATED, UserTasks::STATUS_WAITING, UserTasks::STATUS_REJECTED, UserTasks::STATUS_GET_PROFIT],
                    'showAll' => true,
                    'tasksTagsAppointments' => $tasksTagsAppointments,
                    'tasksTags' => $tasksTags,
                    'user' => $user,
                ]); ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
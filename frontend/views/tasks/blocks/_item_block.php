<?php
/** @var TasksProjects[] $projects */
/** @var Tasks[] $tasks */
/** @var TasksPublishPlace $model */
/** @var UserTasks[] $userTasks */
/** @var array $statuses */
/** @var bool $showAll */
/** @var array $tasksTagsAppointments */
/** @var TasksTags[] $tasksTags */
/** @var User $user */
use common\models\tasks\TasksPublishPlace;
use common\models\tasks\TasksProjects;
use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use common\models\tasks\TasksTagsAppointments;
use common\models\tasks\TasksTags;
use common\models\user\User;

if (!$model->hasVisibility($projects, $tasks, $userTasks, $statuses, $user, $showAll)) {
    return;
}
?>
<div class="fold_block">
    <div class="fold_block__h">
        <h3><?=Yii::t('database', $model->title)?></h3>
    </div>
    <div class="fold_block__content">
        <p>
            <?=Yii::t('database', $model->description)?>
        </p>
        <?php foreach ($projects as $item): ?>
            <?= $this->render('_item_project', [
                    'model' => $item,
                    'tasks' => $tasks,
                    'publishPlaceId' => $model->id,
                    'userTasks' => $userTasks,
                    'statuses' =>  $statuses,
                    'showAll' => $showAll,
                    'tasksTagsAppointments' => $tasksTagsAppointments,
                    'tasksTags' => $tasksTags,
                    'user' => $user,
            ]); ?>
        <?php endforeach; ?>
    </div>
</div>
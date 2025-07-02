<?php
/** @var TasksProjects $model */
/** @var int $publishPlaceId */
/** @var Tasks[] $tasks */
/** @var UserTasks[] $userTasks */
/** @var array $statuses */
/** @var bool $showAll */
/** @var array $tasksTagsAppointments */
/** @var TasksTags[] $tasksTags */
/** @var User $user */

use common\models\tasks\TasksProjects;
use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use common\models\tasks\TasksTagsAppointments;
use common\models\tasks\TasksTags;
use common\models\user\User;

if (!$model->hasVisibility($publishPlaceId, $tasks, $userTasks, $statuses, $user, $showAll)) {
    return;
}

?>
<div class="project_tasks">
    <?php if ($model->is_visibility_name): ?>
        <div class="project_tasks__h" style="gap: 8px;margin-bottom: 8px;">
            <span class="launchpad_ico" style="background-image: url(<?=$model->icon?>);height: 28px;background-size: contain;background-repeat: no-repeat;"></span>
            <h4 style="margin: 0;"><?=Yii::t('database', $model->title)?></h4>
        </div>
    <?php endif; ?>
    <div class="project_tasks__content">
        <?php foreach ($tasks as $item): ?>
            <?=$this->render('_item_task', [
                'model' => $item,
                'projectId' => $model->id,
                'publishPlaceId' => $publishPlaceId,
                'userTasks' => $userTasks,
                'statuses' =>  $statuses,
                'showAll' => $showAll,
                'tasksTagsAppointments' => $tasksTagsAppointments,
                'tasksTags' => $tasksTags,
                'user' => $user,
            ])?>
        <?php endforeach; ?>
    </div>
</div>
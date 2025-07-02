<?php
/** @var Tasks $model */
/** @var int $projectId */
/** @var int $publishPlaceId */
/** @var UserTasks[] $userTasks */
/** @var array $statuses */
/** @var bool $showAll */
/** @var array $tasksTagsAppointments */
/** @var TasksTags[] $tasksTags */
/** @var User $user */

use common\models\tasks\Tasks;
use common\models\user\UserTasks;
use common\models\tasks\TasksTagsAppointments;
use common\models\tasks\TasksTags;
use common\models\user\User;

//if (!empty($userTasks[$model->id]) && $userTasks[$model->id]->status === UserTasks::STATUS_SUCCESS) {
//    return;
//}
if (!$model->hasVisibility($publishPlaceId, $projectId, $userTasks, $statuses, $user, $showAll, $model)) {
    return;
}

$amount = $model->amount;
if (!$model->is_permanent && !empty($userTasks[$model->id]) && in_array($userTasks[$model->id]->status, [UserTasks::STATUS_SUCCESS]) && $userTasks[$model->id]->amount > 0) {
    $amount = round($userTasks[$model->id]->amount, 0);
}

/** @var TasksTagsAppointments $tasksTagsAppointment */
?>
<div
     <?php if ((empty($userTasks[$model->id]) || $userTasks[$model->id]->status !== UserTasks::STATUS_SUCCESS || $model->is_permanent) && $model->is_publish && !$model->is_archive): ?>
     data-href="/tasks/task?id=<?=$model->id?>"
     data-size="modal-md"
         data-toggl="modal"
         data-target="modal-dialog"
     data-title=""
     data-id="<?=$model->id?>"
     class="task_item task_active show-modal-link <?=!empty($userTasks[$model->id]) ? ('status_' . $userTasks[$model->id]->status) : ''?>"
     <?php else: ?>
     style="cursor: default"
     class="task_item <?=!empty($userTasks[$model->id]) ? ('status_' . $userTasks[$model->id]->status) : ''?>"
     <?php endif; ?>>
    <div class="task_item__img">
        <img src="<?=$model->image?>" width="60">
    </div>
    <div class="task_item__content">
        <div class="task_item__h"><?=Yii::t('database', $model->name)?></div>
        <div class="task_item__info">
                <div class="user_points">
                    <div class="task_popup__points">+<?=$amount?></div>
                    <?=$model->amount_icon?>
                </div>
            <?php if (!empty($model->additional_text)): ?>
                <div class="task_popup__reuse_block" style="display:flex; width:110px;">
                    <span class="task_popup__reuse_txt"><?=Yii::t('database', $model->additional_text)?></span>
                    <?php if ($model->is_permanent): ?>
                        <span class="reuse_icon"></span>
                    <?php endif; ?>
                </div>
            <?php elseif ($model->is_permanent): ?>
                <div class="task_popup__reuse_block" style="display:flex; width:20px;">
                    <span class="reuse_icon"></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($tasksTagsAppointments[$model->id])): ?>
                <?php foreach ($tasksTags as $tasksTag): ?>
                    <?php if (!in_array($tasksTag->id, $tasksTagsAppointments[$model->id])) continue; ?>
                    <div class="tag_item" style="background-color: <?=$tasksTag->color_hex?>"><?= Yii::t('database', $tasksTag->title) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($model->is_permanent == 0): ?>
                <div class="task_status_item--completed status_js status_completed"
                     <?php if (empty($userTasks[$model->id]) || $userTasks[$model->id]->status !== UserTasks::STATUS_SUCCESS): ?>style="display: none"<?php endif; ?>>
                    <span class="task_status_item__icon"></span>
                    <span class="task_status_item__label"><?=Yii::t('common', 'Выполнено')?></span>
                </div>
            <?php endif; ?>
            <div class="task_status_item--review status_js status_waiting"
                 <?php if (empty($userTasks[$model->id]) || $userTasks[$model->id]->status !== UserTasks::STATUS_WAITING): ?>style="display: none"<?php endif; ?>>
                <span class="task_status_item__icon"></span>
                <span class="task_status_item__label"><?=Yii::t('common', 'На проверке')?></span>
            </div>
            <div class="task_status_item--review status_js status_reject"
                 <?php if (empty($userTasks[$model->id]) || $userTasks[$model->id]->status !== UserTasks::STATUS_REJECTED): ?>style="display: none"<?php endif; ?>>
                <span class="task_status_item__label" style="color: rgb(222,95,95);"><?=Yii::t('common', 'Отклонено')?></span>
            </div>
            <div class="task_status_item--gift status_js status_gift"
                 <?php if (empty($userTasks[$model->id]) || $userTasks[$model->id]->status !== UserTasks::STATUS_GET_PROFIT): ?>style="display: none"<?php endif; ?>>
                <span class="task_status_item__icon"></span>
                <span class="task_status_item__label"><?=Yii::t('common', 'Получите награду')?></span>
            </div>
        </div>
    </div>
</div>
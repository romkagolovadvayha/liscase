<?php

/** @var TaskCheckForm $model */
/** @var UserTasks $userTask */

use common\models\tasks\TasksPublishPlace;
use common\models\tasks\TasksProjects;
use common\models\tasks\Tasks;
use frontend\forms\tasks\TaskCheckForm;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use common\components\widgets\Alert;
use common\models\user\UserTasks;
use common\models\tasks\TasksTags;
use common\models\tasks\TasksTagsAppointments;

/** @var TasksTagsAppointments[] $tasksTagsAppointmentsData */
$tasksTagsAppointmentsData = TasksTagsAppointments::find()->all();
$tasksTagsAppointments = [];
foreach ($tasksTagsAppointmentsData as $tasksTagsAppointment) {
    if (empty($tasksTagsAppointments[$tasksTagsAppointment->task_id])) {
        $tasksTagsAppointments[$tasksTagsAppointment->task_id] = [];
    }
    $tasksTagsAppointments[$tasksTagsAppointment->task_id][] = $tasksTagsAppointment->tag->id;
}
/** @var TasksTags[] $tasksTags */
$tasksTags = TasksTags::find()
    ->orderBy(['order_index' => SORT_ASC])
    ->all();

$amount = $model->amount;
if (!empty($userTask) && $userTask->status === UserTasks::STATUS_GET_PROFIT && !empty($userTask->amount) && $userTask->amount > 0) {
    $amount = $userTask->amount;
}
if(!$model->is_check_method_auto){
    $model->is_check_method_auto = 0;
}

$js = <<<JS

JS;
$this->registerJs($js);

?>
<style>
    .responsive-video {
        max-width: 100%;
        height: auto;
    }
</style>
<?php Pjax::begin(['id' => 'create-form-pjax', 'linkSelector' => 'a:not([target="_blank"]):not(.no-pjax)', 'enablePushState' => false]); ?>
<?php $form = ActiveForm::begin([
    'enableClientValidation' => false,
    'enableAjaxValidation'   => false,
    'id'                     => 'check-task',
    'options'                => [
        'data-pjax' => 1,
    ],
]); ?>
<div class="grid gap-y-24 px-24 mb-24 task_popup__content">
    <div class="task_popup__img_row">
        <span class="tg_img_item" style="background-image: url(<?= $model->image ?>)"></span>
    </div>
    <?php if (!empty($tasksTagsAppointments[$model->id])): ?>
        <div class="task_item__info">
            <?php foreach ($tasksTags as $tasksTag): ?>
                <?php if (!in_array($tasksTag->id, $tasksTagsAppointments[$model->id])) continue; ?>
                <div class="tag_item" style="background-color: <?= $tasksTag->color_hex ?>"><?= Yii::t('database', $tasksTag->title) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="task_popup__text_row">
        <?php if (!empty($userTask) && in_array($userTask->status, [UserTasks::STATUS_GET_PROFIT])): ?>
            <h3><?= Yii::t('common', 'Поздравляем!') ?></h3>
            <p>
                <?php if (!empty($model->short_name)): ?>
                    <?= Yii::t('database', $model->short_name) ?>
                <?php else: ?>
                    <?= Yii::t('database', $model->name) ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <h3>
                <?php if (!empty($model->short_name)): ?>
                    <?= Yii::t('database', $model->short_name) ?>
                <?php else: ?>
                    <?= Yii::t('database', $model->name) ?>
                <?php endif; ?>
            </h3>
        <?php endif; ?>
        <?php if ($model->is_permanent): ?>
            <div>
                <p style="display: block;"><?= Yii::t('database', $model->description) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($model->additional_explanation): ?>
            <p style="display: block;"><?= Yii::t('database', $model->additional_explanation) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($model->is_email_field && (empty($userTask) || !in_array($userTask->status, [UserTasks::STATUS_GET_PROFIT]))): ?>
        <div class="task_popup__input_row">
            <?= $form->field($model, 'result')->label(false)->textInput(['type' => 'email', 'placeholder' => 'example@mail.com', 'style' => 'display: block;']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($userTask) && in_array($userTask->status, [UserTasks::STATUS_GET_PROFIT])): ?>
        <span class="task_popup__status_txt" style="display: block;color: #59914a;"><?= Yii::t('common', 'Задание выполнено') ?></span>
    <?php else: ?>
        <?php if (!empty($model->button_url)): ?>
            <div class="task_popup__input_row">
                <a class="header__promocode button-secondary button-size__s h-36" style="padding-top: 9px;" target="_blank" onclick="startTask();" href="/tasks/start-task?id=<?= $model->id ?>&type=button">
                    <span class="button__text"><?= Yii::t('database', $model->button_text) ?></span>
                </a>
            </div>
        <?php endif; ?>
        <?php if (!empty($model->url_link)): ?>
            <a class="link_url_partners" onclick="startTask();" href="/tasks/start-task?id=<?= $model->id ?>&type=link" target="_blank"><?= Yii::t('database', $model->url_text) ?></a>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($userTask) && $userTask->status === UserTasks::STATUS_WAITING): ?>
        <span class="task_popup__status_txt" style="display: inline; color: rgb(255, 173, 51);"><?= Yii::t('common', 'Задание отправлено на проверку') ?></span>
    <?php endif; ?>
    <?php if (!empty($userTask) && $userTask->status === UserTasks::STATUS_REJECTED): ?>
        <span class="task_popup__status_txt" style="display: inline; color: rgb(222,95,95);"><?= Yii::t('common', 'Вы не выполнили условия') ?></span>
    <?php endif; ?>
    <?php if (!empty($model->additional_url_text)): ?>
        <a class="link_url_partners" href="<?= $model->additional_url_link ?>" target="_blank"><?= Yii::t('database', $model->additional_url_text) ?></a>
    <?php endif; ?>
    <div class="user_points">
        <h1 class="task_popup__points">
            +<?= number_format($amount, 0) ?>
            <?php if (!empty($model->reward_amount_signature)): ?>
                <?= Yii::t('database', $model->reward_amount_signature) ?>
            <?php endif; ?>
            <?= $model->amount_icon ?>
        </h1>
        <?php if ($amount == $model->amount && !empty($model->additional_text)): ?>
            <span class="task_popup__points_txt" style="display: block;"><?= Yii::t('database', $model->additional_text) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($model->is_permanent): ?>
        <div class="task_popup__reuse_block" style="display: flex;">
            <span class="reuse_icon"></span>
            <span class="task_popup__reuse_txt"><?= Yii::t('common', 'Вознаграждение начисляется за каждое выполнение задания') ?></span>
        </div>
    <?php endif; ?>
</div>
<footer class="px-24 pb-24">
    <?php if (!empty($model->getFirstError('global'))): ?>
        <span class="task_popup__status_txt" style="text-align:center; display: block; color: rgb(222,95,95);margin-bottom: 10px"><?= $model->getFirstError('global') ?></span>
    <?php endif; ?>
    <?= Alert::widget() ?>
    <div class="task_popup__submit_row" style="margin-top: 0;">
        <?php if (!empty($userTask) && $userTask->status === UserTasks::STATUS_WAITING): ?>
            <button class="check_task_btn button-primary w-full" disabled><?= Yii::t('common', 'На проверке') ?></button>
        <?php elseif (!$model->is_permanent && !empty($userTask) && $userTask->status === UserTasks::STATUS_SUCCESS): ?>
            <button class="check_task_btn button-primary w-full" disabled><?= Yii::t('common', 'Выполнено') ?></button>
        <?php elseif (!empty($userTask) && $userTask->status === UserTasks::STATUS_GET_PROFIT): ?>
            <button class="check_task_btn button-primary w-full" type="submit"><?= Yii::t('common', 'Получить награду') ?></button>
        <?php elseif (empty($model->button_url) || $model->is_permanent || (!empty($userTask) && in_array($userTask->status, [UserTasks::STATUS_CREATED, UserTasks::STATUS_REJECTED]))): ?>
            <button class="check_task_btn button-teritiary w-full" type="submit">
                <span class="button__text"><?= Yii::t('common', 'Проверить') ?></span>
            </button>
        <?php else: ?>
            <button class="check_task_btn button-teritiary w-full" type="submit">
                <span class="button__text"><?= Yii::t('common', 'Проверить') ?></span>
            </button>
        <?php endif; ?>
    </div>
</footer>
<script>
    $('.task_item[data-id=' + <?= $model->id ?> + '] .status_js').hide();
    <?php if (!empty($userTask) && $userTask->status === UserTasks::STATUS_WAITING): ?>
        $('.task_item[data-id=' + <?= $model->id ?> + '] .status_js.status_waiting').show();
    <?php elseif (!empty($userTask) && in_array($userTask->status, [UserTasks::STATUS_REJECTED])): ?>
        $('.task_item[data-id=' + <?= $model->id ?> + '] .status_js.status_reject').show();
    <?php elseif (!empty($userTask) && $userTask->status === UserTasks::STATUS_GET_PROFIT): ?>
        $('.task_item[data-id=' + <?= $model->id ?> + '] .status_js.status_gift').show();
    <?php endif; ?>

    if(<?= $model->is_check_method_auto ?>){
        $('.check_task_btn_real').hide();
        $('.link_btn, .link_url_partners').on('click', function() {
            // Нажимаем на кнопку с классом .check_task_btn и типом submit
            $('.check_task_btn').trigger('click');
        });
    }
</script>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
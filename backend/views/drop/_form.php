<?php

use common\models\box\Drop;
use yii\bootstrap5\ActiveForm;
use yii\widgets\Pjax;

/** @var Drop $model */
$this->registerJs(<<<JS
    $(document).on('change', '#dropform-drop_type',  function() {
        $('#box-form').submit();
    });
    $(document).on('click', '#drop-form-submit',  function() {
        $('#drop-form-is-submit-button').val(1);
    });
JS
);
?>

<?php $form = ActiveForm::begin([
                                    'enableClientValidation' => false,
                                    'enableAjaxValidation'   => false,
                                    'id' => 'box-form',
                                    'options' => ['data-pjax' => true, 'enctype' => 'multipart/form-data'],
                                ]); ?>
<?php Pjax::begin([
                      'id'              => 'box-form-pjax',
                      'enablePushState' => false
                  ]); ?>
<div class="row">
    <div class="col-md-4"><?= $form->field($model, 'name')->textInput(); ?></div>
    <div class="col-md-4"><?= $form->field($model, 'rust_id')->textInput(); ?></div>
    <div class="col-md-4"><?= $form->field($model, 'category_id')->dropDownList(\common\models\box\Category::getCategoryList(), []) ?></div>
</div>
<?= $form->field($model, 'description')->textarea(['rows' => 3]); ?>
<div class="row">
    <div class="col-md-6"><?= $form->field($model, 'preview_file')->fileInput(); ?></div>
    <div class="col-md-6"><?= $form->field($model, 'preview_file_open')->fileInput(); ?></div>
</div>
<div class="row">
    <div class="col-md-4"><?= $form->field($model, 'market_status')->dropDownList(Drop::getMarketStatusList(), []) ?></div>
    <div class="col-md-4"><?= $form->field($model, 'show_main_block')->dropDownList([0 => 'Нет', 1 => 'Да'], []); ?></div>
    <div class="col-md-4">
        <?= $form->field($model, 'blocked_hour')->dropDownList([
                                                                   '' => 'Нет блока',
                                                                   2 => '2 часа',
                                                                   4 => '4 часа',
                                                                   6 => '6 часов',
                                                                   12 => '12 часов',
                                                                   24 => '24 часа',
                                                               ], []); ?>
    </div>
</div>
<div class="row">
    <div class="col-md-2"><?= $form->field($model, 'price')->textInput(); ?></div>
    <div class="col-md-2"><?= $form->field($model, 'discount')->textInput(['value' => $model->isNewRecord ? 0 : $model->discount]); ?></div>
    <div class="col-md-2"><?= $form->field($model, 'count')->textInput(['value' => $model->isNewRecord ? 1 : $model->count]); ?></div>
    <div class="col-md-2"><?= $form->field($model, 'floating_price_percent')->textInput(); ?></div>
    <div class="col-md-4"><?= $form->field($model, 'eng_name')->textInput(); ?></div>
</div>
<div>
    <?= $form->field($model, 'is_blocked_building')->dropDownList([
                                                                      1 => 'Да',
                                                                      0 => 'Нет',
                                                                  ], []); ?>
</div>

<?= $form->field($model, 'drop_type')->dropDownList(Drop::getDropTypesList(), []) ?>
<?php if (in_array($model->drop_type, [Drop::TYPE_COMMAND, Drop::TYPE_VIP])): ?>
    <?= $form->field($model, 'command')->textarea()
        ->hint($model->drop_type == Drop::TYPE_VIP ? 'Команда будет выполнена на сервере при выдаче VIP. Используйте %STEAMID% для подстановки Steam ID пользователя.' : ''); ?>
<?php endif; ?>
<?php if (in_array($model->drop_type, [Drop::TYPE_SET])): ?>
    <div>
        <?= $form->field($model, 'full_only')->dropDownList([
                                                                1 => 'Да',
                                                                0 => 'Нет',
                                                            ], []); ?>
    </div>
<?php endif; ?>
<?php if (in_array($model->drop_type, [Drop::TYPE_SET, Drop::TYPE_SELECT])): ?>
    <div class="form-group">
        <a href="/drop-drop/create?dropId=<?=$model->id?>" class="btn btn-primary show-modal-link"
           data-toggl="modal"
           data-target="modal-dialog"
           data-title="Добавить предмет">Добавить предмет</a>
    </div>
    <div style="display: flex; flex-direction: column; gap: 5px;margin-top: 10px;">
        <?php foreach ($model->subDrops as $subDrop): ?>
            <div style="display: flex; gap: 10px;align-items: center;padding: 10px;border-radius: 8px;background: hsl(0, 0%, 11.8%);justify-content: space-between">
                <div style="display: flex; gap: 10px;align-items: center;">
                    <img src="<?=$subDrop->drop->image()?>" width="32px"/> <?=$subDrop->drop->name?> (x<?=$subDrop->count?>)
                </div>
                <div style="display: flex; gap: 10px;align-items: center;">
                    <a href="" style="color: red">Убрать</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?=$this->render('list-drop-stat', [
    'dropId' => $model->id,
])?>

<div class="form-group">
    <a href="/drop-stat/create?dropId=<?=$model->id?>" class="btn btn-primary show-modal-link"
       data-toggl="modal"
       data-target="modal-dialog"
       data-title="Добавить в статистику">Добавить в статистику</a>
</div>

<div style="margin-top: 10px;">
    <?= $form->field($model, 'isSubmit')
             ->label(false)
             ->hiddenInput(['id' => 'drop-form-is-submit-button', 'value' => 0]); ?>
    <div class="form-group">
        <?= \yii\helpers\Html::submitButton(
            Yii::t('common', 'Сохранить'),
            ['class' => 'btn btn-primary', 'id' => 'drop-form-submit']
        ) ?>
    </div>
</div>

<?php Pjax::end(); ?>
<?php ActiveForm::end(); ?>

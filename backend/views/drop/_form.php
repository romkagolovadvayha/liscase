<?php

use common\models\box\Drop;
use yii\bootstrap5\ActiveForm;

/** @var Drop $model */
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'name')->textInput(); ?>
<?= $form->field($model, 'rust_id')->textInput(); ?>
<?= $form->field($model, 'category_id')->dropDownList(\common\models\box\Category::getCategoryList(), []) ?>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
<?= $form->field($model, 'preview_file_open')->fileInput(); ?>
<?= $form->field($model, 'market_status')->dropDownList(Drop::getMarketStatusList(), []) ?>
<?= $form->field($model, 'command')->textarea(); ?>
<?= $form->field($model, 'blocked_hour')->dropDownList([
        '' => 'Нет блока',
        2 => '2 часа',
        4 => '4 часа',
        6 => '6 часов',
        12 => '12 часов',
        24 => '24 часа',
], []); ?>
<?= $form->field($model, 'show_main_block')->dropDownList([0 => 'Нет', 1 => 'Да'], []); ?>
<?= $form->field($model, 'price')->textInput(); ?>
<?= $form->field($model, 'sort')->textInput(); ?>

<div class="accordion mb-3" id="accordionPanelsStayOpenExample">
    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingTwo">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false" aria-controls="panelsStayOpen-collapseTwo">
                Дополнительно
            </button>
        </h2>
        <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingTwo">
            <div class="accordion-body">

                <?= $form->field($model, 'discount')->textInput(['value' => $model->isNewRecord ? 0 : $model->discount]); ?>
                <?= $form->field($model, 'count')->textInput(['value' => $model->isNewRecord ? 1 : $model->count]); ?>
                <?= $form->field($model, 'type_id')->dropDownList(\common\models\box\DropType::getTypeList(), []) ?>
                <?= $form->field($model, 'min_box')->textInput(['value' => $model->isNewRecord ? 0 : $model->min_box]); ?>
                <?= $form->field($model, 'max_box')->textInput(['value' => $model->isNewRecord ? 0 : $model->max_box]); ?>

            </div>
        </div>
    </div>
</div>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>

<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var common\models\medals\Medal $model */

$this->title = $model->isNewRecord ? 'Создать медаль' : 'Изменить медаль';
$this->params['breadcrumbs'][] = ['label' => 'Медали', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="medals-form-page p-4 lg:p-6">
    <div class="max-w-3xl bg-[hsl(0_0%_15%_/_1)] rounded-lg p-5">
        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
        <?= $form->field($model, 'name')->textInput(['class' => 'ds-input w-full']) ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 5, 'class' => 'ds-input w-full']) ?>
        <?php if (!$model->isNewRecord && $model->image_path): ?>
            <img src="<?= Html::encode($model->getImageUrl()) ?>" alt="" width="96" height="96" class="mb-3 object-contain">
        <?php endif; ?>
        <?= $form->field($model, 'imageFile')->fileInput(['accept' => 'image/*', 'class' => 'ds-input w-full']) ?>
        <?= $form->field($model, 'is_active')->checkbox() ?>
        <div class="flex gap-2 mt-4">
            <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

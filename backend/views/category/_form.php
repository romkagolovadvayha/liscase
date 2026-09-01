<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\box\Category $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="category-form">
    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <?= $form->field($model, 'name')->textInput() ?>
    <?= $form->field($model, 'tag')->textInput() ?>
    <div class="ds-select-wrapper">
        <?= $form->field($model, 'show_main_block', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([0 => 'Нет', 1 => 'Да'], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow" aria-hidden="true"></i>
    </div>
    <?= $form->field($model, 'sort')->textInput() ?>
    
    <?= $form->field($model, 'image')->fileInput(['accept' => 'image/*']) ?>
    
    <?php if ($model->image): ?>
        <div class="form-group">
            <label class="control-label">Текущее изображение:</label>
            <div>
                <?php 
                $imageUrl = $model->getImageUrl();
                if ($imageUrl): 
                ?>
                    <img src="<?= Html::encode($imageUrl) ?>" alt="Изображение категории" class="admin-form-image-preview" />
                <?php else: ?>
                    <p class="text-muted">Изображение не найдено</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

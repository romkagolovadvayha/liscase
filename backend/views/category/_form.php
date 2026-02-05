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
    <?= $form->field($model, 'show_main_block')->dropDownList([0 => 'Нет', 1 => 'Да'], []); ?>
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
                    <img src="<?= Html::encode($imageUrl) ?>" alt="Изображение категории" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px; margin-top: 10px;" />
                <?php else: ?>
                    <p class="text-muted">Изображение не найдено</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

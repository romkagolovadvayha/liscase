<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogCategory $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="blog-category-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput() ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <?= $form->field($model, 'blog_category_id')->dropDownList(\common\models\blog\BlogCategory::getCategories(), [
        'prompt' => Yii::t('common', 'Не выбрано...'),
    ]) ?>

    <?= $form->field($model, 'status')->dropDownList(\common\models\blog\BlogCategory::getStatusList()) ?>

    <div class="form-group mt-3">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

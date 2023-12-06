<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="blog-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <?= $form->field($model, 'content')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'blog_category_id')->dropDownList(\common\models\blog\BlogCategory::getChildsCategories(), [
        'prompt' => Yii::t('common', 'Не выбрано...'),
    ]) ?>

    <?= $form->field($model, 'status')->dropDownList(\common\models\blog\BlogCategory::getStatusList()) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

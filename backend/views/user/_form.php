<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var \common\models\user\User $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-form">

    <?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'store')->dropDownList([
                                                           0       => Yii::t('common', 'Нет'),
                                                           1      => Yii::t('common', 'Да'),
                                                       ], []) ?>
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php

use common\models\box\Drop;
use yii\bootstrap5\ActiveForm;

/** @var Drop $model */

?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'drop-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'market_link')->textInput(); ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>

<?php

use backend\models\TelegramRecipients;
use common\models\user\User;
use kartik\form\ActiveForm;
/** @var TelegramRecipients $model */

if($model->ref_id && is_string($model->ref_id)){
    $model->ref_id = json_decode($model->ref_id,1);
}
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'telegram-recipients-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'ref_id')->widget(\kartik\select2\Select2::class,
    [
        'data'    => User::getActiveUsersRefCodes($model->ref_id),
        'options' => [
            'prompt' => '...',
            'multiple' => true
        ],
        'pluginOptions' => [
            'allowClear' => true,
            'maximumSelectionLength' => 50,
            'minimumInputLength' => 3
        ],
        'showToggleAll' => false
    ]); ?>
<?= $form->field($model, 'name')->textInput()?>
<?= $this->context->getFormButtons(); ?>
<?php ActiveForm::end(); ?>

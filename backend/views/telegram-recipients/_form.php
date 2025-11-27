<?php

use backend\models\TelegramRecipients;
use common\models\user\User;
use kartik\form\ActiveForm;
/** @var TelegramRecipients $model */

if($model->ref_id && is_string($model->ref_id)){
    $model->ref_id = json_decode($model->ref_id,1);
}
?>

<div class="ds-card">
    <div class="ds-card__header">
        <h5 class="ds-card__header-title"><?= $model->isNewRecord ? 'Создать список получателей' : 'Редактировать список получателей' ?></h5>
    </div>
    <div class="ds-card__body">
        <?php $form = ActiveForm::begin(
            [
                'id' => 'telegram-recipients-form',
                'options' => ['enctype' => 'multipart/form-data']
            ]); ?>

        <?= \yii\helpers\Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger', 'encode' => false]) ?>

        <?= $form->field($model, 'ref_id')->widget(\kartik\select2\Select2::class,
            [
                'data'    => User::getActiveUsersRefCodes($model->ref_id),
                'options' => [
                    'prompt' => 'Выберите пользователей...',
                    'multiple' => true,
                    'placeholder' => 'Выберите пользователей...',
                ],
                'pluginOptions' => [
                    'allowClear' => true,
                    'maximumSelectionLength' => 50,
                    'minimumInputLength' => 3
                ],
                'showToggleAll' => false
            ])->label('Пользователи <span class="text-danger">*</span>'); ?>
        
        <?= $form->field($model, 'name')->textInput([
            'placeholder' => 'Например: VIP пользователи'
        ])->label('Название списка <span class="text-danger">*</span>') ?>
        
        <div class="form-group mt-4">
            <div class="ds-flex ds-items-center ds-gap-md">
                <?= $this->context->getFormButtons(); ?>
            </div>
        </div>
        
        <?php ActiveForm::end(); ?>
    </div>
</div>

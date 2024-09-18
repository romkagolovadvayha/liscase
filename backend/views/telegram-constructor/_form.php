<?php
use backend\models\TelegramConstructor;
use yii\bootstrap5\ActiveForm;

/** @var TelegramConstructor $model */

$this->registerJs('
    $(document).on("change", "#countries_actions_tg, #countries_id_tg", function () {
        if($("#countries_actions_tg").val())
        {
            $("#countries_id_tg").prop("disabled", "disabled");
            console.log($("#countries_actions_tg").val()); 
        } else {
            $("#countries_id_tg").prop("disabled", false);
        }
        
        if($("#countries_id_tg").val().length)
        {
            $("#countries_actions_tg").prop("disabled", "disabled");
            console.log($("#countries_id_tg").val().length);          
        } else {
            $("#countries_actions_tg").prop("disabled", false);
        }
              
        
    });
');
?>

<div class="row">
    <div class="col-lg-3 col-md-6 col-sm-8 col-xs-12">
        <?php $form = ActiveForm::begin(
            [
                'id' => 'telegram-constructor-form',
                'options' => ['enctype' => 'multipart/form-data']
            ]); ?>


        <?= $form->field($model, 'title')->textInput() ?>
        <?= $form->field($model, 'audience_id')->widget(\kartik\select2\Select2::class, [
            'data'    => TelegramConstructor::getAudienceList(),
            'options' => [
                'prompt' => 'Выберите аудиторию...',
            ],
            'showToggleAll' => false
        ]); ?>
        <?= $form->field($model, 'bot_id')->widget(\kartik\select2\Select2::class, [
            'data'    => TelegramConstructor::getBotList(),
            'options' => [
                'prompt' => 'Выберите бота...',
            ],
            'showToggleAll' => false
        ]); ?>
        <?= $form->field($model, 'telegram_constructor_message_id')->widget(\kartik\select2\Select2::class, [
            'data'    => \backend\models\TelegramConstructorMessage::getList(),
            'options' => [
                'prompt' => 'Выберите сообщение...',
            ],
            'showToggleAll' => false
        ]); ?>
        <?= $this->context->getFormButtons(); ?>
        <?php ActiveForm::end(); ?>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-8 col-xs-12">
        <div class="constructor_message_preview"></div>
    </div>
</div>

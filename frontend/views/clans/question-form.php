<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var \frontend\forms\clans\QuestionForm $model */
/** @var ActiveForm $form */
?>

<?php Pjax::begin(
    [
        'id'              => 'question-clan-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'question-clan-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<?= Alert::widget() ?>
<div class="px-24 mb-24">
    <div class="form-group">
        <?=$form->field($model, 'description', [
            'inputOptions' => [
                'class' => 'form-control',
                'rows' => '3',
            ],
            //            'template' => "{input}"
        ])
                ->label(false)
                ->textarea(['placeholder' => Yii::t('common', 'Расскажите о себе...')]); ?>
    </div>
    <div class="form-group">
        <?= $form->field($model, 'social_discord')->label(false)->textInput(['placeholder' => Yii::t('common', 'Ваш Discord...')]) ?>
    </div>
    <div class="form-group">
        <?= $form->field($model, 'social_vk')->label(false)->textInput(['placeholder' => Yii::t('common', 'Ваш VK...')]) ?>
    </div>
    <div class="form-group">
        <?= $form->field($model, 'social_youtube')->label(false)->textInput(['placeholder' => Yii::t('common', 'Ваш Youtube...')]) ?>
    </div>
    <div class="form-group">
        <?= $form->field($model, 'social_twitch')->label(false)->textInput(['placeholder' => Yii::t('common', 'Ваш Twitch...')]) ?>
    </div>
</div>
<footer class="px-24 pb-24">
    <div class="modal_form_product_buttons">
        <button class="button-primary w-full" id="buy_product" type="submit">
            <span class="button__text"><?=Yii::t('common', 'Отправить заявку')?></span>
        </button>
    </div>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>
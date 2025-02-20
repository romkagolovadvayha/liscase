<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var \frontend\forms\clans\ClanForm $model */
/** @var ActiveForm $form */
?>

<?php Pjax::begin(
    [
        'id'              => 'create-clan-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'create-clan-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<?= Alert::widget() ?>
<div class="px-24 mb-24">
    <div class="form-group">
        <?= $form->field($model, 'title')->label(false)->textInput(['placeholder' => Yii::t('common', 'Название клана...')]) ?>
    </div>
    <div class="form-group">
        <?=$form->field($model, 'description_short', [
            'inputOptions' => [
                'class' => 'form-control',
                'rows' => '3',
            ],
            //            'template' => "{input}"
        ])
                ->label(false)
                ->textarea(['placeholder' => Yii::t('common', 'Краткое описание...')]); ?>
    </div>
</div>
<footer class="px-24 pb-24">
    <div class="modal_form_product_buttons">
        <button class="button-primary w-full" id="buy_product" type="submit">
            <span class="button__text"><?=Yii::t('common', 'Создать')?></span>
        </button>
    </div>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>
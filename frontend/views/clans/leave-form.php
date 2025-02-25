<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var \frontend\forms\clans\ClanForm $model */
/** @var ActiveForm $form */
?>

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
<footer class="px-24 pb-24">
    <p class="p1 text-text-teritiary text-center mb-24"><?=Yii::t('common', 'Вы действительно хотите выйти из клана? Войти обратно можно будет только по приглашению одного из участников.')?></p>
    <div class="modal_form_product_buttons">
        <button class="button-primary w-full" id="buy_product" type="submit">
            <span class="button__text"><?=Yii::t('common', 'Выйти из клана')?></span>
        </button>
    </div>
</footer>
<?php ActiveForm::end(); ?>
<div class="modal_preloader" id="product-loader"></div>
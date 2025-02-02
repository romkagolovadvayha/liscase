<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Drop $drop */
/** @var \frontend\forms\user\ReportForm $reportForm */
/** @var bool $reportExist */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

?>

<?php Pjax::begin(
    [
        'id'              => 'buy-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'buy-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<?= Alert::widget() ?>
<footer class="px-24 pb-24">
    <?php if (Yii::$app->user->isGuest): ?>
        <button class="button-danger w-full">
            <a href="/auth/oauth?authclient=steam" class="button__text" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </button>
    <?php elseif ($reportExist): ?>
        <p class="p1 text-text-teritiary relative z-1"><?=Yii::t('common', 'Жалоба на игрока уже отправлена, спасибо!')?></p>
    <?php else: ?>
        <div class="form-group">
        <?=$form->field($reportForm, 'text', [
            'inputOptions' => [
                'class' => 'form-control',
                'rows' => '4',
            ],
//            'template' => "{input}"
        ])
                ->label(false)
                ->textarea(['placeholder' => Yii::t('common', 'Введите описание ситуации которая произошла...')]); ?>
        </div>
        <div class="modal_form_product_buttons">
            <button class="button-primary w-full" id="buy_product" type="submit">
                <span class="button__text"><?=Yii::t('common', 'Отправить жалобу')?></span>
            </button>
        </div>
    <?php endif; ?>
</footer>
<div class="modal_preloader" id="product-loader"></div>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
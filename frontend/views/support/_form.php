<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use frontend\widgets\Alert;

/** @var yii\web\View $this */
/** @var common\models\support\Support $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="modal_form_product">
    <?php if (Yii::$app->user->isGuest): ?>
        <div class="market_entity_card_alert" style="margin-top: 0">
            <div class="market_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
            <div class="market_entity_card_alert_text"><?=Yii::t('common', 'Для создания тикета необходимо пройти авторизацию')?></div>
        </div>
        <div class="modal_form_product_buttons" style="justify-content: center;">
            <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" style="display: block;width: auto;flex: none;" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </div>
    <?php else: ?>
        <?php Pjax::begin(
            [
                'id'              => 'buy-container-pjax',
                'enablePushState' => false
            ]
        ); ?>
        <?= Alert::widget() ?>
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
        <?= $form->field($model, 'server_tag')->dropDownList(\common\models\servers\Servers::getServers(), [
            'prompt' => Yii::t('common', 'Не выбрано...'),
        ]) ?>
        <div class="modal_form_product_buttons">
            <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Закрыть')?></button>
            <?= Html::submitButton('Создать', ['class' => 'btn btn-success']) ?>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    <?php endif; ?>
</div>

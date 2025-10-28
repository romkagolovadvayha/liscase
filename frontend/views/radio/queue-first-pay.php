<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $track \common\models\radio\RadioTrack */
/* @var $price int */

$this->title = Yii::t('common', 'Поставить трек первым в очередь');
?>

<div class="transfer-form grid gap-y-24 px-24 mb-24">
    <p class="transfer-form__text">
        <?= Yii::t('common', 'Вы хотите поставить трек "{title}" первым в очередь?', ['title' => Html::encode($track->title)]) ?>
    </p>
    
    <div class="transfer-form__balance">
        <span><?= Yii::t('common', 'С вашего баланса спишется') ?>:</span>
        <span class="line_sum_munus">
            <?= number_format($price, 0, '.', ' ') ?> 
            <span class="icons icons_16px icons_16px_coin"></span>
        </span>
    </div>
    
    <?php $form = ActiveForm::begin(['options' => ['class' => 'transfer-form__form']]); ?>
    
    <div class="transfer-form__buttons">
        <?= Html::submitButton(Yii::t('common', 'Оплатить'), ['class' => 'button button-primary']) ?>
        <button type="button" class="button button-secondary" data-bs-dismiss="modal"><?= Yii::t('common', 'Отмена') ?></button>
    </div>
    
    <?php ActiveForm::end(); ?>
</div>


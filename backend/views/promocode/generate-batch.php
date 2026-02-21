<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var int $quantity */
/** @var int $amount */

$this->title = Yii::t('common', 'Сгенерировать одноразовые промокоды');
$model = new \yii\base\DynamicModel(['quantity' => $quantity, 'amount' => $amount]);
$model->addRule(['quantity', 'amount'], 'required');
$model->addRule(['quantity'], 'integer', ['min' => 1, 'max' => 1000]);
$model->addRule(['amount'], 'integer', ['min' => 0]);
$model->load(Yii::$app->request->post(), '');
?>
<div class="promocode-generate-batch p-4 max-w-md">
    <p class="text-gray-400 text-sm mb-4">Создаются бессрочные одноразовые промокоды. После ввода пользователем промокод станет неактивным.</p>
    <?php $form = ActiveForm::begin(['id' => 'generate-batch-form']); ?>
    <?= $form->field($model, 'quantity')->textInput(['type' => 'number', 'min' => 1, 'max' => 1000, 'class' => 'ds-input'])->label(Yii::t('common', 'Количество')) ?>
    <?= $form->field($model, 'amount')->textInput(['type' => 'number', 'min' => 0, 'class' => 'ds-input'])->label(Yii::t('common', 'Сумма (RUB)')) ?>
    <div class="flex gap-2 mt-4">
        <?= Html::submitButton(Yii::t('common', 'Сгенерировать'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a(Yii::t('common', 'К списку'), ['/promocode/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php

use common\models\invoice\Deposit;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */
?>

<div class="deposit-form deposit-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'deposit-form',
        'options' => ['class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 deposit-form-content">
        <?= $form->field($model, 'amount', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'min' => 1]) ?>

        <?= $form->field($model, 'payment_id', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 4, 'class' => 'ds-textarea form-control']) ?>

        <?= $form->field($model, 'created_at', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control']) ?>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        </div>
    </div>

    <!-- Правая колонка: параметры -->
    <aside class="deposit-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="space-y-3">
                    <?php if (!$model->isNewRecord): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">ID</label>
                        <div class="text-white text-sm"><?= (int)$model->id ?></div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('user_id') ?></label>
                        <div class="text-white text-sm"><?= (int)$model->user_id ?></div>
                        <?php if ($model->user): ?>
                            <a href="<?= \yii\helpers\Url::to(['/user/profile', 'userId' => $model->user_id]) ?>" class="text-blue-400 hover:underline text-sm"><?= Html::encode($model->user->username) ?></a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('payment_type') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'payment_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getTypeList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <?php else: ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('payment_type') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'payment_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getTypeList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('user_id') ?></label>
                        <?= $form->field($model, 'user_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

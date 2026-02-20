<?php

use backend\models\DepositsSearch;
use common\models\invoice\Deposit;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var DepositsSearch $searchModel */
$searchModel = $searchModel ?? null;
if (!$searchModel) return;
?>
<aside class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>
    <div class="p-4 flex-1">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Фильтры') ?></h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('username') ?></label>
                    <?= $form->field($searchModel, 'username', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm', 'placeholder' => $searchModel->getAttributeLabel('username'),
                    ]) ?>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('steam_id') ?></label>
                    <?= $form->field($searchModel, 'steam_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm', 'placeholder' => $searchModel->getAttributeLabel('steam_id'),
                    ]) ?>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('payment_type') ?></label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'payment_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            ArrayHelper::merge(['' => Yii::t('common', 'Любой')], Deposit::getTypeList()),
                            ['class' => 'ds-select', 'prompt' => Yii::t('common', 'Любой')]
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('status') ?></label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            ArrayHelper::merge(['' => Yii::t('common', 'Любой')], Deposit::getStatusList()),
                            ['class' => 'ds-select', 'prompt' => Yii::t('common', 'Любой')]
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('amount') ?></label>
                    <?= $form->field($searchModel, 'amount', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm', 'type' => 'number', 'placeholder' => $searchModel->getAttributeLabel('amount'),
                    ]) ?>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block"><?= $searchModel->getAttributeLabel('payment_id') ?></label>
                    <?= $form->field($searchModel, 'payment_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm', 'placeholder' => $searchModel->getAttributeLabel('payment_id'),
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm w-full justify-center"><i class="fas fa-filter"></i> <?= Yii::t('common', 'Применить') ?></button>
        <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center mt-2"><i class="fas fa-redo"></i> <?= Yii::t('common', 'Сбросить') ?></a>
    </div>
    <?php ActiveForm::end(); ?>
</aside>

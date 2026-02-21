<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;

/** @var \common\models\promocode\PromocodeSearch $searchModel */
$searchModel = $searchModel ?? null;
if (!$searchModel) return;
?>
<aside class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <?php $form = ActiveForm::begin([
        'action' => empty($searchModel->tab) ? ['index'] : ['index', 'tab' => $searchModel->tab],
        'method' => 'get',
        'enableClientValidation' => false,
        'id' => 'promocode-search-form',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>
    <?php if (!empty($searchModel->tab)): ?>
        <?= \yii\helpers\Html::hiddenInput('tab', $searchModel->tab) ?>
    <?php endif; ?>
    <div class="p-4 flex-1">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Фильтры</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Код</label>
                    <?= $form->field($searchModel, 'code', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm', 'placeholder' => 'Промокод',
                    ]) ?>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Статус</label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            ArrayHelper::merge(['' => 'Все'], Promocode::getStatusList()),
                            ['class' => 'ds-select', 'prompt' => 'Все']
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm w-full justify-center"><i class="fas fa-filter"></i> Применить</button>
        <a href="<?= Url::to(!empty($searchModel->tab) ? ['index', 'tab' => $searchModel->tab] : ['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center mt-2"><i class="fas fa-redo"></i> Сбросить</a>
    </div>
    <?php ActiveForm::end(); ?>
</aside>

<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\grid\GridView;
use common\models\servers\Servers;
use yii\helpers\ArrayHelper;

/** @var backend\models\ServersSearch $searchModel */
?>

<!-- Filters Panel for Servers -->
<aside class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'id' => 'servers-search-form',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>
    
    <div class="p-4 flex-1">
        <!-- Фильтры Section -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">
                Фильтры
            </h3>
            
            <div class="space-y-3">
                <!-- ID -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">ID</label>
                    <?= $form->field($searchModel, 'id', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'type' => 'number',
                        'placeholder' => 'ID сервера',
                    ]) ?>
                </div>

                <!-- Статус -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Статус</label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'status', [
                            'options' => ['class' => 'mb-0'],
                            'template' => '{input}',
                        ])->dropDownList(
                            ArrayHelper::merge(['' => 'Все'], Servers::getStatusList()),
                            [
                                'class' => 'ds-select',
                                'prompt' => 'Все',
                            ]
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>

                <!-- Wipe -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Wipe</label>
                    <?= $form->field($searchModel, 'wipe', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'type' => 'date',
                    ]) ?>
                </div>

                <!-- Next Wipe -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Следующий Wipe</label>
                    <?= $form->field($searchModel, 'next_wipe', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'type' => 'date',
                    ]) ?>
                </div>

                <!-- Global Wipe -->
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Global Wipe</label>
                    <?= $form->field($searchModel, 'global_wipe', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'type' => 'date',
                    ]) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <div class="space-y-2">
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm w-full justify-center">
                <i class="fas fa-filter"></i>
                <span>Применить фильтры</span>
            </button>
            <a href="<?= \yii\helpers\Url::to(['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center">
                <i class="fas fa-redo"></i>
                <span>Сбросить</span>
            </a>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</aside>


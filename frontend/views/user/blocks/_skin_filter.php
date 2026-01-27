<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $form yii\widgets\ActiveForm */
/* @var $model \frontend\modules\user\SkinsSearch */
/* @var $type string */

// Получаем уникальные качества/типы из данных для фильтра
$qualities = [];
if ($type == 'rust') {
    $items = \Yii::$app->rustTm->items();
    // Для Rust используем ru_quality (тип предмета)
    foreach ($items as $item) {
        if (!empty($item['ru_quality']) && !in_array($item['ru_quality'], $qualities)) {
            $qualities[] = $item['ru_quality'];
        }
    }
    sort($qualities);
    // Переводим типы предметов на русский
    $qualitiesList = [];
    foreach ($qualities as $quality) {
        $qualitiesList[$quality] = \common\components\rusttm\RustTm::translateItemType($quality);
    }
} else {
    $items = \Yii::$app->csGoMarket->items();
    // Для CS2 используем ru_quality (качество)
    foreach ($items as $item) {
        if (!empty($item['ru_quality']) && !in_array($item['ru_quality'], $qualities)) {
            $qualities[] = $item['ru_quality'];
        }
    }
    sort($qualities);
    $qualitiesList = array_combine($qualities, $qualities);
}

// Добавляем опцию "Все типы" в начало списка
$allTypesKey = 'all_types';
$allTypesLabel = Yii::t('common', 'Все типы');
$qualitiesList = [$allTypesKey => $allTypesLabel] + $qualitiesList;

$sortOptions = [
    'price_asc' => Yii::t('common', 'Цена: по возрастанию'),
    'price_desc' => Yii::t('common', 'Цена: по убыванию'),
    'name_asc' => Yii::t('common', 'Название: А-Я'),
    'name_desc' => Yii::t('common', 'Название: Я-А'),
    'popularity' => Yii::t('common', 'Популярность'),
];
?>

<?php $form = ActiveForm::begin([
    'method' => 'GET',
    'id' => 'skins-filter-form',
    'options' => [
        'data-pjax' => 1,
        'class' => 'skins-page__filter-form',
    ],
]); ?>

<div class="skins-page__filter-row">
    <!-- Поиск по названию -->
    <div class="skins-page__filter-search">
        <div class="skins-page__filter-search-wrapper">
            <i class="fas fa-search skins-page__filter-search-icon"></i>
            <?= $form->field($model, 'name', [
                'template' => '{input}',
                'options' => ['class' => 'skins-page__filter-search-field']
            ])->textInput([
                'placeholder' => Yii::t('common', 'Поиск по названию...'),
                'class' => 'skins-page__filter-search-input',
                'autocomplete' => 'off'
            ]) ?>
            <button type="submit" class="skins-page__filter-search-submit" title="<?= Yii::t('common', 'Найти') ?>">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- Фильтр по качеству/типу -->
    <?php if (!empty($qualitiesList)): ?>
        <div class="skins-page__filter-quality">
            <?php
            // Преобразуем quality в массив, если это строка
            $qualityValue = $model->quality;
            if (!empty($qualityValue) && !is_array($qualityValue)) {
                $qualityValue = [$qualityValue];
            } elseif (empty($qualityValue)) {
                $qualityValue = [];
            }
            ?>
            <?= $form->field($model, 'quality')->widget(\kartik\select2\Select2::class, [
                'data' => $qualitiesList,
                'value' => $qualityValue,
                'options' => [
                    'placeholder' => $type == 'rust' ? Yii::t('common', 'Тип предмета') : Yii::t('common', 'Качество'),
                    'id' => 'quality-select',
                    'multiple' => true,
                ],
                'pluginOptions' => [
                    'allowClear' => true,
                    'theme' => 'default',
                    'closeOnSelect' => false,
                ],
                'pluginEvents' => [
                    "change" => "function(e){ 
                        var \$select = \$(e.target);
                        var selected = \$select.val() || [];
                        var allTypesKey = '{$allTypesKey}';
                        var allKeys = " . json_encode(array_keys($qualitiesList)) . ";
                        var realKeys = allKeys.filter(function(k) { return k !== allTypesKey; });
                        var isProcessing = \$select.data('processing');
                        
                        if (isProcessing) {
                            return;
                        }
                        
                        \$select.data('processing', true);
                        
                        // Если выбран 'Все типы'
                        if (selected.indexOf(allTypesKey) !== -1) {
                            // Если уже выбраны все реальные ключи, то снимаем все
                            var allRealSelected = realKeys.every(function(k) {
                                return selected.indexOf(k) !== -1;
                            });
                            if (allRealSelected) {
                                // Снимаем все через Select2 API без вызова события
                                \$select.select2('val', []);
                                setTimeout(function() {
                                    \$select.data('processing', false);
                                    \$('#skins-filter-form').submit();
                                }, 50);
                                return;
                            } else {
                                // Выбираем все реальные ключи через Select2 API
                                var newSelected = [allTypesKey].concat(realKeys);
                                \$select.select2('val', newSelected);
                                setTimeout(function() {
                                    \$select.data('processing', false);
                                    \$('#skins-filter-form').submit();
                                }, 50);
                                return;
                            }
                        }
                        
                        // Если выбраны все реальные ключи, добавляем 'Все типы'
                        var allRealSelected = realKeys.every(function(k) {
                            return selected.indexOf(k) !== -1;
                        });
                        if (allRealSelected && selected.length === realKeys.length) {
                            var newSelected = [allTypesKey].concat(realKeys);
                            \$select.select2('val', newSelected);
                            setTimeout(function() {
                                \$select.data('processing', false);
                                \$('#skins-filter-form').submit();
                            }, 50);
                            return;
                        }
                        
                        // Убираем 'Все типы', если он был выбран, но выбраны не все
                        if (selected.indexOf(allTypesKey) !== -1) {
                            selected = selected.filter(function(k) { return k !== allTypesKey; });
                            \$select.select2('val', selected);
                            setTimeout(function() {
                                \$select.data('processing', false);
                                \$('#skins-filter-form').submit();
                            }, 50);
                            return;
                        }
                        
                        \$select.data('processing', false);
                        \$('#skins-filter-form').submit();
                    }",
                ],
            ])->label(false) ?>
        </div>
    <?php endif; ?>

    <!-- Фильтр по цене -->
    <div class="skins-page__filter-price">
        <div class="skins-page__filter-price-row">
            <?= $form->field($model, 'price_min', [
                'template' => '{input}',
                'options' => ['class' => 'skins-page__filter-price-field']
            ])->textInput([
                'type' => 'number',
                'placeholder' => Yii::t('common', 'От'),
                'min' => 0,
                'class' => 'skins-page__filter-price-input',
            ]) ?>
            <span class="skins-page__filter-price-separator">—</span>
            <?= $form->field($model, 'price_max', [
                'template' => '{input}',
                'options' => ['class' => 'skins-page__filter-price-field']
            ])->textInput([
                'type' => 'number',
                'placeholder' => Yii::t('common', 'До'),
                'min' => 0,
                'class' => 'skins-page__filter-price-input',
            ]) ?>
        </div>
    </div>

    <!-- Сортировка -->
    <div class="skins-page__filter-sort">
        <?php
        // Устанавливаем значение по умолчанию, если не задано
        if (empty($model->sort)) {
            $model->sort = 'price_asc';
        }
        ?>
        <?= $form->field($model, 'sort')->widget(\kartik\select2\Select2::class, [
            'data' => $sortOptions,
            'value' => $model->sort,
            'options' => [
                'placeholder' => Yii::t('common', 'Сортировка'),
                'id' => 'sort-select',
            ],
            'pluginOptions' => [
                'allowClear' => false,
                'theme' => 'default',
            ],
            'pluginEvents' => [
                "change" => "function(){ \$('#skins-filter-form').submit(); }",
            ],
        ])->label(false) ?>
    </div>

</div>

<?php ActiveForm::end(); ?>
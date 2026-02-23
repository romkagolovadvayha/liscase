<?php

use common\models\servers\Servers;
use common\models\servers\ServersTags;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */

$selectedTags = $model->isNewRecord ? [] : $model->getTagIds();
?>

<div class="servers-form servers-form--compact w-full flex flex-col min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'servers-form',
        'options' => [
            'class' => 'flex flex-col min-h-0 flex-1 w-full',
        ],
    ]); ?>

    <?= Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger mb-4 mx-4 lg:mx-6 flex-shrink-0', 'encode' => false]) ?>

    <!-- Основная колонка + Параметры в одной строке (как в форме предмета) -->
    <div class="servers-form-layout flex flex-col lg:flex-row min-h-0 flex-1 w-full">
    <div class="flex-1 min-w-0 p-4 lg:p-6 servers-form-content overflow-y-auto">
        <div class="space-y-6">
            <div class="flex flex-wrap gap-3">
                <?= $form->field($model, 'name', ['options' => ['class' => 'mb-0 flex-1 min-w-[160px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Название сервера']) ?>
                <?= $form->field($model, 'monitoring_name', ['options' => ['class' => 'mb-0 flex-1 min-w-[160px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Название в мониторинге']) ?>
                <?= $form->field($model, 'monitoring_description', ['options' => ['class' => 'mb-0 flex-1 min-w-[160px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Описание в мониторинге']) ?>
            </div>

            <div class="flex flex-wrap gap-3">
                <?= $form->field($model, 'tag', ['options' => ['class' => 'mb-0 flex-1 min-w-[100px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Тег']) ?>
                <?= $form->field($model, 'min_map_size', ['options' => ['class' => 'mb-0 w-24'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Мин. карта']) ?>
                <?= $form->field($model, 'max_map_size', ['options' => ['class' => 'mb-0 w-24'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Макс. карта']) ?>
                <?= $form->field($model, 'max', ['options' => ['class' => 'mb-0 w-24'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Слотов']) ?>
                <?= $form->field($model, 'team_limit', ['options' => ['class' => 'mb-0 w-24'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Лимит']) ?>
                <?= $form->field($model, 'rust_app_id', ['options' => ['class' => 'mb-0 w-28'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'RustApp ID']) ?>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Вайп</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php $wipeInputId = Html::getInputId($model, 'wipe'); ?>
                    <div class="mb-0 servers-wipe-field-wrap relative">
                        <?= $form->field($model, 'wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}<div class="flex gap-1 flex-nowrap">{input}<button type="button" class="servers-wipe-date-btn ds-btn ds-btn--secondary flex-shrink-0 px-2" title="Подставить дату" aria-expanded="false" aria-haspopup="true" data-input-id="' . $wipeInputId . '"><i class="fas fa-calendar-alt"></i></button></div>{error}'])->textInput(['class' => 'ds-input form-control flex-1 min-w-0', 'type' => 'datetime-local']) ?>
                        <div class="servers-wipe-date-dropdown absolute top-full left-0 mt-1 z-10 hidden min-w-[240px] bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded shadow-lg py-1" data-input-id="<?= $wipeInputId ?>">
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="1">Сегодня 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="2">Пятница этой недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="3">Пятница следующей недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="4">Первый четверг следующего месяца 21:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="5">Понедельник 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="6">Понедельник через неделю 16:00</button>
                        </div>
                    </div>
                    <?php $nextWipeInputId = Html::getInputId($model, 'next_wipe'); ?>
                    <div class="mb-0 servers-wipe-field-wrap relative">
                        <?= $form->field($model, 'next_wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}<div class="flex gap-1 flex-nowrap">{input}<button type="button" class="servers-wipe-date-btn ds-btn ds-btn--secondary flex-shrink-0 px-2" title="Подставить дату" aria-expanded="false" aria-haspopup="true" data-input-id="' . $nextWipeInputId . '"><i class="fas fa-calendar-alt"></i></button></div>{error}'])->textInput(['class' => 'ds-input form-control flex-1 min-w-0', 'type' => 'datetime-local']) ?>
                        <div class="servers-wipe-date-dropdown absolute top-full left-0 mt-1 z-10 hidden min-w-[240px] bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded shadow-lg py-1" data-input-id="<?= $nextWipeInputId ?>">
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="1">Сегодня 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="2">Пятница этой недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="3">Пятница следующей недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="4">Первый четверг следующего месяца 21:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="5">Понедельник 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="6">Понедельник через неделю 16:00</button>
                        </div>
                    </div>
                    <?php $globalWipeInputId = Html::getInputId($model, 'global_wipe'); ?>
                    <div class="mb-0 servers-wipe-field-wrap relative">
                        <?= $form->field($model, 'global_wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}<div class="flex gap-1 flex-nowrap">{input}<button type="button" class="servers-wipe-date-btn ds-btn ds-btn--secondary flex-shrink-0 px-2" title="Подставить дату" aria-expanded="false" aria-haspopup="true" data-input-id="' . $globalWipeInputId . '"><i class="fas fa-calendar-alt"></i></button></div>{error}'])->textInput(['class' => 'ds-input form-control flex-1 min-w-0', 'type' => 'datetime-local']) ?>
                        <div class="servers-wipe-date-dropdown absolute top-full left-0 mt-1 z-10 hidden min-w-[240px] bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded shadow-lg py-1" data-input-id="<?= $globalWipeInputId ?>">
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="1">Сегодня 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="2">Пятница этой недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="3">Пятница следующей недели 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="4">Первый четверг следующего месяца 21:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="5">Понедельник 16:00</button>
                            <button type="button" class="servers-wipe-date-opt w-full text-left px-3 py-2 text-sm text-zinc-300 hover:bg-[hsl(0_0%_25%_/_1)]" data-option="6">Понедельник через неделю 16:00</button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Подключение</h3>
                <div class="flex flex-wrap gap-3">
                    <?= $form->field($model, 'ip', ['options' => ['class' => 'mb-0 flex-1 min-w-[120px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'IP']) ?>
                    <?= $form->field($model, 'text_ip', ['options' => ['class' => 'mb-0 flex-1 min-w-[120px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Текстовой IP (prostoj.store)']) ?>
                    <?= $form->field($model, 'port', ['options' => ['class' => 'mb-0 w-20'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Порт']) ?>
                    <?= $form->field($model, 'query', ['options' => ['class' => 'mb-0 w-20'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Query']) ?>
                    <?= $form->field($model, 'rcon', ['options' => ['class' => 'mb-0 w-20'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'RCON']) ?>
                    <?= $form->field($model, 'rcon_password', ['options' => ['class' => 'mb-0 flex-1 min-w-[120px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'password', 'placeholder' => 'Пароль RCON']) ?>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Теги сервера</h3>
                <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Теги') ?></label>
                <?= Select2::widget([
                    'name' => 'server_tags',
                    'value' => $selectedTags,
                    'data' => ServersTags::getTagsList(),
                    'options' => [
                        'placeholder' => Yii::t('common', 'Выберите теги...'),
                        'multiple' => true,
                        'class' => 'form-control',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'tags' => false,
                    ],
                ]); ?>
            </div>

            <div class="flex flex-wrap gap-2 items-center pt-2">
                <?= Html::submitButton('<i class="fas fa-check"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('<i class="fas fa-times"></i> ' . Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            </div>
        </div>
    </div>

    <!-- Правая колонка: Параметры (на всю высоту как в форме предмета) -->
    <aside class="servers-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] min-h-0 overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col min-h-0">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Параметры</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(Servers::getStatusList(), ['class' => 'ds-select w-full text-sm', 'prompt' => '']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('wipe_type') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'wipe_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList([
                                7 => Yii::t('common', 'Еженедельно'),
                                14 => Yii::t('common', 'Каждые две недели'),
                                30 => Yii::t('common', 'Раз в месяц'),
                            ], ['class' => 'ds-select w-full text-sm', 'prompt' => '']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-1">
                        <span class="text-sm text-zinc-300 flex-1 min-w-0"><?= $model->getAttributeLabel('secret_map') ?></span>
                        <?= Html::activeHiddenInput($model, 'secret_map', ['value' => 0, 'id' => null]) ?>
                        <label class="ds-switch flex-shrink-0" for="<?= Html::getInputId($model, 'secret_map') ?>">
                            <?= Html::activeCheckbox($model, 'secret_map', ['value' => 1, 'label' => false, 'uncheck' => false]) ?>
                            <span class="ds-switch__slider"></span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-1">
                        <span class="text-sm text-zinc-300 flex-1 min-w-0"><?= $model->getAttributeLabel('skindrops') ?></span>
                        <?= Html::activeHiddenInput($model, 'skindrops', ['value' => 0, 'id' => null]) ?>
                        <label class="ds-switch flex-shrink-0" for="<?= Html::getInputId($model, 'skindrops') ?>">
                            <?= Html::activeCheckbox($model, 'skindrops', ['value' => 1, 'label' => false, 'uncheck' => false]) ?>
                            <span class="ds-switch__slider"></span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-1">
                        <span class="text-sm text-zinc-300 flex-1 min-w-0"><?= $model->getAttributeLabel('is_store') ?></span>
                        <?= Html::activeHiddenInput($model, 'is_store', ['value' => 0, 'id' => null]) ?>
                        <label class="ds-switch flex-shrink-0" for="<?= Html::getInputId($model, 'is_store') ?>">
                            <?= Html::activeCheckbox($model, 'is_store', ['value' => 1, 'label' => false, 'uncheck' => false]) ?>
                            <span class="ds-switch__slider"></span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-1">
                        <span class="text-sm text-zinc-300 flex-1 min-w-0"><?= $model->getAttributeLabel('hidden_store') ?></span>
                        <?= Html::activeHiddenInput($model, 'hidden_store', ['value' => 0, 'id' => null]) ?>
                        <label class="ds-switch flex-shrink-0" for="<?= Html::getInputId($model, 'hidden_store') ?>">
                            <?= Html::activeCheckbox($model, 'hidden_store', ['value' => 1, 'label' => false, 'uncheck' => false]) ?>
                            <span class="ds-switch__slider"></span>
                        </label>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('secret_key') ?></label>
                        <?= $form->field($model, 'secret_key', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('discord_token') ?></label>
                        <?= $form->field($model, 'discord_token', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('commands') ?></label>
                        <?= $form->field($model, 'commands', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'placeholder' => 'Через запятую']) ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>
    </div><!-- /.servers-form-layout -->

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<'JS'
(function() {
    function pad(n) { return n < 10 ? '0' + n : n; }
    function toDatetimeLocal(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function getSuggestedDate(option) {
        var now = new Date();
        var d;
        switch (String(option)) {
            case '1': // Сегодня 16:00
                d = new Date(now);
                d.setHours(16, 0, 0, 0);
                break;
            case '2': // Пятница этой недели 16:00 (0=Вс, 5=Пт)
                d = new Date(now);
                var day = d.getDay();
                var toFri = day <= 5 ? (5 - day) : -1;
                d.setDate(d.getDate() + toFri);
                d.setHours(16, 0, 0, 0);
                break;
            case '3': // Пятница следующей недели 16:00
                d = new Date(now);
                day = d.getDay();
                toFri = day <= 5 ? (5 - day) + 7 : 6;
                d.setDate(d.getDate() + toFri);
                d.setHours(16, 0, 0, 0);
                break;
            case '4': // Первый четверг следующего месяца 21:00 (четверг = 4)
                d = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                while (d.getDay() !== 4) d.setDate(d.getDate() + 1);
                d.setHours(21, 0, 0, 0);
                break;
            case '5': // Понедельник 16:00 (следующий понедельник, 1=Пн)
                d = new Date(now);
                day = d.getDay();
                var toMon = day === 0 ? 1 : (8 - day);
                d.setDate(d.getDate() + toMon);
                d.setHours(16, 0, 0, 0);
                break;
            case '6': // Понедельник через неделю 16:00
                d = new Date(now);
                day = d.getDay();
                toMon = day === 0 ? 1 : (8 - day);
                d.setDate(d.getDate() + toMon + 7);
                d.setHours(16, 0, 0, 0);
                break;
            default:
                return null;
        }
        return toDatetimeLocal(d);
    }

    $(document).on('click', '.servers-wipe-date-btn', function() {
        var btn = $(this);
        var inputId = btn.data('input-id');
        var drop = $('.servers-wipe-date-dropdown[data-input-id="' + inputId + '"]');
        var isOpen = drop.hasClass('dropdown-open');
        $('.servers-wipe-date-dropdown').removeClass('dropdown-open').addClass('hidden');
        $('.servers-wipe-date-btn').attr('aria-expanded', 'false');
        if (!isOpen) {
            drop.removeClass('hidden').addClass('dropdown-open');
            btn.attr('aria-expanded', 'true');
        }
    });

    $(document).on('click', '.servers-wipe-date-opt', function() {
        var opt = $(this);
        var inputId = opt.closest('.servers-wipe-date-dropdown').data('input-id');
        var value = getSuggestedDate(opt.data('option'));
        if (value && inputId) {
            $('#' + inputId).val(value);
        }
        $('.servers-wipe-date-dropdown').removeClass('dropdown-open').addClass('hidden');
        $('.servers-wipe-date-btn').attr('aria-expanded', 'false');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.servers-wipe-field-wrap').length) {
            $('.servers-wipe-date-dropdown').removeClass('dropdown-open').addClass('hidden');
            $('.servers-wipe-date-btn').attr('aria-expanded', 'false');
        }
    });
})();
JS
);
?>

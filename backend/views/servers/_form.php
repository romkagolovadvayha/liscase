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

<div class="servers-form servers-form--compact w-full">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'servers-form',
        'options' => [
            'class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full',
        ],
    ]); ?>

    <?= Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger mb-4 mx-4 lg:mx-6', 'encode' => false]) ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 servers-form-content">
        <div class="space-y-6">
            <?= $form->field($model, 'name', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Название сервера']) ?>

            <?= $form->field($model, 'description', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 4, 'class' => 'ds-textarea form-control', 'placeholder' => 'Описание сервера']) ?>

            <?= $form->field($model, 'rules', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 4, 'class' => 'ds-textarea form-control', 'placeholder' => 'Правила сервера']) ?>

            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Вайп</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2">
                    <?= $form->field($model, 'wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'datetime-local']) ?>
                    <?= $form->field($model, 'next_wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'datetime-local']) ?>
                    <?= $form->field($model, 'global_wipe', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'datetime-local']) ?>
                </div>
                <?= $form->field($model, 'wipe_server_description', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 2, 'class' => 'ds-textarea form-control', 'placeholder' => 'Описание при вайпе']) ?>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Подключение</h3>
                <div class="flex flex-wrap gap-3">
                    <?= $form->field($model, 'ip', ['options' => ['class' => 'mb-0 flex-1 min-w-[140px]'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'placeholder' => 'IP']) ?>
                    <?= $form->field($model, 'port', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'placeholder' => 'Порт']) ?>
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

    <!-- Правая колонка: Параметры -->
    <aside class="servers-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Параметры</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('tag') ?></label>
                        <?= $form->field($model, 'tag', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'placeholder' => 'Тег']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(Servers::getStatusList(), ['class' => 'ds-select w-full text-sm', 'prompt' => '']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('sort') ?></label>
                        <?= $form->field($model, 'sort', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('monitoring_name') ?></label>
                        <?= $form->field($model, 'monitoring_name', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('monitoring_description') ?></label>
                        <?= $form->field($model, 'monitoring_description', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('game_mode') ?></label>
                        <?= $form->field($model, 'game_mode', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'placeholder' => 'vanilla']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('monitoring_tags') ?></label>
                        <?= $form->field($model, 'monitoring_tags', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('rust_app_id') ?></label>
                        <?= $form->field($model, 'rust_app_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('wargm_id') ?></label>
                        <?= $form->field($model, 'wargm_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
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
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('wipe_server_name') ?></label>
                        <?= $form->field($model, 'wipe_server_name', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('min_map_size') ?></label>
                        <?= $form->field($model, 'min_map_size', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('max_map_size') ?></label>
                        <?= $form->field($model, 'max_map_size', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('secret_map') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'secret_map', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList([0 => Yii::t('common', 'Нет'), 1 => Yii::t('common', 'Да')], ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('text_ip') ?></label>
                        <?= $form->field($model, 'text_ip', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'placeholder' => 'prostoj.store']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('query') ?></label>
                        <?= $form->field($model, 'query', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('rcon') ?></label>
                        <?= $form->field($model, 'rcon', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('rcon_password') ?></label>
                        <?= $form->field($model, 'rcon_password', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'password']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('max') ?></label>
                        <?= $form->field($model, 'max', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('team_limit') ?></label>
                        <?= $form->field($model, 'team_limit', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('skindrops') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'skindrops', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList([0 => Yii::t('common', 'Нет'), 1 => Yii::t('common', 'Да')], ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('is_store') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'is_store', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList([0 => Yii::t('common', 'Нет'), 1 => Yii::t('common', 'Да')], ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
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

    <?php ActiveForm::end(); ?>
</div>

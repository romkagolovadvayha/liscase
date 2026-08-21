<?php

use common\models\servers\ServersTags;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersTags $model */
?>

<div class="servers-tags-form servers-tags-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'servers-tags-form',
        'method' => 'post',
        'options' => ['class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <?php if ($model->hasErrors()): ?>
        <div class="ds-alert ds-alert--danger mb-4 mx-4 lg:mx-6">
            <?= Html::errorSummary($model, ['encode' => false]) ?>
        </div>
    <?php endif; ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 servers-tags-form-content">
        <?= $form->field($model, 'name', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'title', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'link_name', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'short_description', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'description', ['options' => ['class' => 'mb-2 blog-form-tinymce-wrap']])->widget(\dosamigos\tinymce\TinyMce::class, [
            'options' => ['rows' => 12],
            'language' => 'ru',
            'clientOptions' => [
                'license_key' => 'gpl',
                'plugins' => [
                    'advlist','autolink','lists','link','media',
                    'table','codesample','code','emoticons','paste','autoresize','quickbars'
                ],
                'toolbar' => 'undo redo | styles | bold italic underline | ' .
                    'alignleft aligncenter alignright alignjustify | ' .
                    'bullist numlist outdent indent | table | link image media | ' .
                    'codesample code emoticons',
                'menubar' => 'file edit view insert format tools table',
                'statusbar' => true,
                'resize' => true,
                'default_link_target' => '_blank',
                'link_context_toolbar' => true,
                'convert_urls' => false,
            ],
        ]) ?>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </div>

    <!-- Правая колонка: Параметры -->
    <aside class="servers-tags-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="space-y-3">
                    <?php if (!$model->isNewRecord): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">ID</label>
                        <div class="text-white text-sm"><?= (int)$model->id ?></div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('color') ?></label>
                        <?= $form->field($model, 'color', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput([
                            'class' => 'ds-input w-full text-sm',
                            'maxlength' => true,
                            'value' => $model->isNewRecord ? '#3498db' : $model->color,
                        ]) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('icon') ?></label>
                        <?= $form->field($model, 'icon', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput([
                            'class' => 'ds-input w-full text-sm',
                            'placeholder' => 'star, heart, shield',
                        ]) ?>
                        <p class="text-gray-500 text-xs mt-1"><?= Yii::t('common', 'Название иконки из компонента Icon') ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('sort') ?></label>
                        <?= $form->field($model, 'sort', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(ServersTags::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

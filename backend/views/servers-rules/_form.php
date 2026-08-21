<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\servers\ServersRules;
use common\models\servers\ServersRulesCategory;
use common\models\servers\Servers;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRules $model */
?>

<div class="servers-rules-form servers-rules-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'id' => 'servers-rules-form',
        'method' => 'post',
        'enableClientValidation' => true,
        'enableAjaxValidation' => false,
        'options' => ['class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <?php if ($model->hasErrors()): ?>
        <div class="ds-alert ds-alert--danger mb-4 mx-4 lg:mx-6">
            <?= Html::errorSummary($model, ['encode' => false]) ?>
        </div>
    <?php endif; ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 servers-rules-form-content">
        <div class="ds-select-wrapper mb-2">
            <?= $form->field($model, 'category_id', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(
                ArrayHelper::map(ServersRulesCategory::find()->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])->all(), 'id', 'name'),
                ['class' => 'ds-select form-control', 'prompt' => Yii::t('common', 'Выберите категорию')]
            ) ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>

        <?php $serversList = ArrayHelper::map(Servers::find()->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])->all(), 'id', 'name'); ?>
        <?= $form->field($model, 'serverIds', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{hint}{error}'])->checkboxList($serversList, [
            'item' => function ($index, $label, $name, $checked, $value) {
                return '<label class="flex items-center gap-2 py-1.5 px-2 rounded hover:bg-[hsl(0_0%_18%_/_1)] cursor-pointer">' .
                    Html::checkbox($name, $checked, ['value' => $value, 'id' => 'server-' . $value]) .
                    '<span class="text-sm text-gray-300">' . Html::encode($label) . '</span></label>';
            },
        ])->hint(Yii::t('common', 'Если ничего не выбрано, правило будет общим для всех серверов.')) ?>

        <?= $form->field($model, 'title', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{hint}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true])->hint(Yii::t('common', 'Опционально. Название правила, если нужно')) ?>

        <?= $form->field($model, 'content', ['options' => ['class' => 'mb-2 blog-form-tinymce-wrap'], 'template' => '{label}{input}{hint}{error}'])->widget(\dosamigos\tinymce\TinyMce::class, [
            'options' => ['rows' => 10],
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
        ])->hint(Yii::t('common', 'Содержание правила в формате HTML')) ?>

        <div class="flex flex-wrap gap-4 mb-2">
            <div class="flex-1 min-w-0" style="min-width: 200px;">
                <?= $form->field($model, 'punishment', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{hint}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true])->hint(Yii::t('common', 'Например: [ban], [mute], [ban 14d]')) ?>
            </div>
            <div style="width: 100px;">
                <?= $form->field($model, 'sort', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number']) ?>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </div>

    <!-- Правая колонка: Параметры -->
    <aside class="servers-rules-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="space-y-3">
                    <?php if (!$model->isNewRecord): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">ID</label>
                        <div class="text-white text-sm"><?= (int)$model->id ?></div>
                    </div>
                    <?php if ($model->created_at): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('created_at') ?></label>
                        <div class="text-white text-sm"><?= Html::encode($model->created_at) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($model->updated_at): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('updated_at') ?></label>
                        <div class="text-white text-sm"><?= Html::encode($model->updated_at) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

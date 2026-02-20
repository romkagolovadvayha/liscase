<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\servers\ServersRulesCategory;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRulesCategory $model */
?>

<div class="servers-rules-category-form servers-rules-category-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'id' => 'servers-rules-category-form',
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
    <div class="flex-1 min-w-0 p-4 lg:p-6 servers-rules-category-form-content">
        <?= $form->field($model, 'name', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'no_numbering', ['options' => ['class' => 'mb-2'], 'template' => '{input}{label}{error}{hint}'])->checkbox([
            'label' => Yii::t('common', 'Без нумерации правил'),
        ])->hint(Yii::t('common', 'Если включено, правила в этой категории не будут иметь нумерацию (например, для категории «Команды на сервере»)') ?>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </div>

    <!-- Правая колонка: Параметры -->
    <aside class="servers-rules-category-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
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
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('icon') ?></label>
                        <?= $form->field($model, 'icon', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput([
                            'class' => 'ds-input w-full text-sm',
                            'maxlength' => true,
                            'placeholder' => 'shield, user-shield, terminal',
                        ]) ?>
                        <p class="text-gray-500 text-xs mt-1"><?= Yii::t('common', 'Название иконки из компонента Icon') ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('sort') ?></label>
                        <?= $form->field($model, 'sort', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm', 'type' => 'number']) ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

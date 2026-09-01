<?php

use common\models\building\Building;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */
?>

<div class="building-form building-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'building-form',
        'options' => ['class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 building-form-content">
        <?= $form->field($model, 'name', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <?= $form->field($model, 'description', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 3, 'class' => 'ds-textarea form-control']) ?>

        <?= $form->field($model, 'location', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

        <div class="ds-select-wrapper mb-2">
            <?= $form->field($model, 'server_tag', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(\common\models\servers\Servers::getServers(), ['class' => 'ds-select form-control']) ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        </div>
    </div>

    <!-- Правая колонка: параметры и превью изображения -->
    <aside class="building-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
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
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Building::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1"><?= Yii::t('common', 'Превью') ?></div>
                        <?php
                        $firstImage = $model->buildingImage ? reset($model->buildingImage) : null;
                        if ($firstImage):
                            $previewUrl = $firstImage->getPublicUrlPreview();
                        ?>
                        <div class="building-form-preview rounded overflow-hidden bg-[hsl(0_0%_15.3%_/_1)]">
                            <a href="<?= Html::encode(\yii\helpers\Url::to(['view', 'id' => $model->id])) ?>" target="_blank" rel="noopener" class="block">
                                <img src="<?= Html::encode($previewUrl) ?>" alt="Превью постройки <?= Html::encode($model->name) ?>" class="building-form-preview__image" />
                            </a>
                        </div>
                        <p class="text-xs text-gray-400 mt-1"><?= count($model->buildingImage) ?> <?= Yii::t('common', 'фото') ?></p>
                        <?php else: ?>
                        <div class="building-form-preview__empty text-gray-500 text-sm py-4 border border-dashed border-[hsl(0_0%_15.3%_/_1)] rounded flex items-center justify-center">
                            <?= Yii::t('common', 'Нет фото') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

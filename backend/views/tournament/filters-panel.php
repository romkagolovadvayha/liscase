<?php

use backend\models\TournamentSearch;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var TournamentSearch $searchModel */
$searchModel = $searchModel ?? null;
if (!$searchModel) {
    return;
}

$serverList = ArrayHelper::merge(
    ['' => Yii::t('common', 'Все серверы')],
    ArrayHelper::map(
        Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
        'id',
        'name'
    )
);
?>
<div class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'enableClientValidation' => false,
        'id' => 'tournament-search-form',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>
    <div class="p-4 flex-1">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Фильтры') ?></h3>
            <div class="space-y-3">
                <div>
                    <label for="tournamentsearch-title" class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Название') ?></label>
                    <?= $form->field($searchModel, 'title', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'placeholder' => Yii::t('common', 'Поиск…'),
                    ]) ?>
                </div>
                <div>
                    <label for="tournamentsearch-slug" class="text-xs text-gray-400 mb-1 block">Slug</label>
                    <?= $form->field($searchModel, 'slug', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->textInput([
                        'class' => 'ds-input w-full text-sm',
                    ]) ?>
                </div>
                <div>
                    <label for="tournamentsearch-status" class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Статус') ?></label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            ArrayHelper::merge(['' => Yii::t('common', 'Все')], Tournament::getStatusList()),
                            ['class' => 'ds-select w-full text-sm']
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
                <div>
                    <label for="tournamentsearch-phase" class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Фаза') ?></label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'phase', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            ArrayHelper::merge(['' => Yii::t('common', 'Все')], Tournament::getPhaseList()),
                            ['class' => 'ds-select w-full text-sm']
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
                <div>
                    <label for="tournamentsearch-server_id" class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Сервер') ?></label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'server_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}'])->dropDownList(
                            $serverList,
                            ['class' => 'ds-select w-full text-sm']
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm w-full justify-center">
            <i class="fas fa-filter"></i> <?= Yii::t('common', 'Применить') ?>
        </button>
        <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center mt-2">
            <i class="fas fa-redo"></i> <?= Yii::t('common', 'Сбросить') ?>
        </a>
    </div>
    <?php ActiveForm::end(); ?>
</div>

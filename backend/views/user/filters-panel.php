<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use common\models\user\User;
use common\models\user\UserSearch;

/** @var UserSearch $searchModel */
$searchModel = $searchModel ?? null;
if (!$searchModel) {
    return;
}
?>

<aside class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto flex flex-col">
<style>
@media (min-width: 992px) { .user-filters-mobile-header { display: none !important; } }
@media (max-width: 991px) { .user-filters-mobile-header { display: flex !important; } }
</style>
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'id' => 'user-search-form',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>

    <!-- Заголовок с кнопкой закрытия (только на мобилке) -->
    <div class="user-filters-mobile-header flex-shrink-0" style="align-items: center; justify-content: space-between; gap: 12px; padding: 16px; border-bottom: 1px solid hsl(0 0% 15.3% / 1);">
        <h3 class="text-sm font-semibold text-white uppercase tracking-wide m-0">Фильтры</h3>
        <button type="button" class="filters-drawer-close ds-btn ds-btn--icon ds-btn--ghost" aria-label="Закрыть" style="min-width: 44px; min-height: 44px; padding: 0;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="p-4 flex-1 min-h-0 overflow-y-auto">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide hidden lg:block">Фильтры</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">ID</label>
                    <?= $form->field($searchModel, 'id', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'type' => 'number',
                        'placeholder' => 'ID пользователя',
                    ]) ?>
                </div>

                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Ник</label>
                    <?= $form->field($searchModel, 'username', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'placeholder' => 'Поиск по нику',
                    ]) ?>
                </div>

                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Steam ID</label>
                    <?= $form->field($searchModel, 'steam_id', [
                        'options' => ['class' => 'mb-0'],
                        'template' => '{input}',
                    ])->textInput([
                        'class' => 'ds-input w-full text-sm',
                        'placeholder' => 'Steam ID',
                    ]) ?>
                </div>

                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Статус</label>
                    <div class="ds-select-wrapper">
                        <?= $form->field($searchModel, 'status', [
                            'options' => ['class' => 'mb-0'],
                            'template' => '{input}',
                        ])->dropDownList(
                            ArrayHelper::merge(['' => 'Все'], User::getStatusList()),
                            [
                                'class' => 'ds-select',
                                'prompt' => 'Все',
                            ]
                        ) ?>
                        <i class="fas fa-chevron-down ds-select-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <div class="space-y-2">
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm w-full justify-center">
                <i class="fas fa-filter"></i>
                <span>Применить фильтры</span>
            </button>
            <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center">
                <i class="fas fa-redo"></i>
                <span>Сбросить</span>
            </a>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</aside>

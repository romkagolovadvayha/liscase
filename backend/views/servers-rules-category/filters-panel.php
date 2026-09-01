<?php

use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var backend\models\ServersRulesCategorySearch $searchModel */
$searchModel = $searchModel ?? null;
if (!$searchModel) return;
?>
<div class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'options' => ['class' => 'h-full flex flex-col'],
    ]); ?>
    <div class="p-4 flex-1">
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">Фильтры</h3>
            <p class="text-gray-500 text-xs">Нет фильтров</p>
        </div>
    </div>
    <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
        <a href="<?= Url::to(['index']) ?>" class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-center block text-center"><i class="fas fa-redo"></i> Сбросить</a>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php

use yii\helpers\Html;

/** @var common\models\battle_pass\BattlePassSeason[] $models */

$this->title = 'Сезоны Battle Pass';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="battle-pass-admin-index">
    <?= \frontend\widgets\Alert::widget() ?>
    <div class="battle-pass-admin-list">
        <?php foreach ($models as $model): ?>
            <article class="battle-pass-admin-season">
                <div class="battle-pass-admin-season__number">S<?= (int)$model->season_number ?></div>
                <div class="battle-pass-admin-season__content">
                    <strong><?= Html::encode($model->name) ?></strong>
                    <span><?= Html::encode($model->starts_at) ?><?= $model->ends_at ? ' — ' . Html::encode($model->ends_at) : '' ?></span>
                    <small><?= (int)$model->getTasks()->count() ?> заданий · <?= $model->medal ? Html::encode($model->medal->name) : 'без медали' ?></small>
                </div>
                <span class="ds-badge <?= $model->status === 'active' ? 'ds-badge--success' : 'ds-badge--secondary' ?>"><?= Html::encode($model->getStatusList()[$model->status] ?? $model->status) ?></span>
                <?= Html::a('Задания', ['/tasks-v2/index', 'type' => 'battle_pass', 'season_id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
                <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<style>
.battle-pass-admin-index{padding:16px 24px;background:hsl(0 0% 10%);min-height:100%}.battle-pass-admin-list{display:grid;gap:10px}.battle-pass-admin-season{display:flex;align-items:center;gap:14px;background:hsl(0 0% 15%);border-radius:10px;padding:14px}.battle-pass-admin-season__number{font-size:22px;font-weight:800;color:hsl(18 90% 60%);min-width:52px}.battle-pass-admin-season__content{display:grid;gap:2px;min-width:0;flex:1}.battle-pass-admin-season__content span,.battle-pass-admin-season__content small{color:hsl(0 0% 62%);font-size:12px}@media(max-width:760px){.battle-pass-admin-season{align-items:flex-start;flex-wrap:wrap}.battle-pass-admin-season__content{flex-basis:calc(100% - 70px)}}
</style>

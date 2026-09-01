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
                <?= Html::a('<i class="fas fa-pen" aria-hidden="true"></i>', ['update', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--secondary ds-btn--sm',
                    'aria-label' => 'Редактировать сезон «' . $model->name . '»',
                    'title' => 'Редактировать',
                ]) ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<?php

use common\models\servers\Servers;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

/** @var Servers $model */
$statusLabel = ArrayHelper::getValue(Servers::getStatusList(), $model->status);
$statusClass = $model->status == Servers::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
$updatedText = '';
if ($model->status == Servers::STATUS_ACTIVE && !empty($model->updated_at)) {
    $updatedText = (time() - strtotime($model->updated_at)) . ' ' . Yii::t('common', 'сек. назад');
}
?>
<div class="servers-index-card">
    <div class="servers-index-card__head">
        <span class="servers-index-card__name"><?= Html::encode($model->name) ?></span>
        <span class="ds-badge <?= $statusClass ?>"><?= Html::encode($statusLabel) ?></span>
    </div>
    <div class="servers-index-card__meta">
        <span class="servers-index-card__id">#<?= (int)$model->id ?></span>
        <?php if (!empty($model->tag)): ?>
            <span class="servers-index-card__tag"><?= Html::encode($model->tag) ?></span>
        <?php endif; ?>
    </div>
    <?php if (!empty($model->wipe) || !empty($model->next_wipe) || !empty($model->global_wipe)): ?>
    <div class="servers-index-card__wipes">
        <?php if (!empty($model->wipe)): ?>
            <div class="servers-index-card__row">
                <span class="servers-index-card__label"><?= Yii::t('common', 'Последний вайп') ?></span>
                <span class="servers-index-card__value"><?= Html::encode($model->wipe) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($model->next_wipe)): ?>
            <div class="servers-index-card__row">
                <span class="servers-index-card__label"><?= Yii::t('common', 'Следующий вайп') ?></span>
                <span class="servers-index-card__value"><?= Html::encode($model->next_wipe) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($model->global_wipe)): ?>
            <div class="servers-index-card__row">
                <span class="servers-index-card__label"><?= Yii::t('common', 'Глобальный вайп') ?></span>
                <span class="servers-index-card__value"><?= Html::encode($model->global_wipe) ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($updatedText !== ''): ?>
        <div class="servers-index-card__updated"><?= Html::encode($updatedText) ?></div>
    <?php endif; ?>
    <div class="servers-index-card__action">
        <?= Html::a(Yii::t('common', 'Изменить'), Url::toRoute(['update', 'id' => $model->id]), ['class' => 'ds-btn ds-btn--primary ds-btn--sm']) ?>
    </div>
</div>

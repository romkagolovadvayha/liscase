<?php

use common\models\serverskin\ServerSkin;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var ServerSkin $model */
/** @var int $index */

$imgUrl = null;
if (!empty($model->image)) {
    try {
        $imgUrl = $model->getImagePubUrl();
    } catch (\Throwable $e) {
    }
}
$statusList = ServerSkin::getStatusList();
$statusLabel = ArrayHelper::getValue($statusList, $model->status, '');
$statusBadgeClass = $model->status == ServerSkin::STATUS_ACTIVE ? 'ds-badge--success' : ($model->status == ServerSkin::STATUS_WAIT ? 'ds-badge--warning' : 'ds-badge--danger');
?>
<div class="server-skin-index-card">
    <div class="server-skin-index-card__preview">
        <?php if (!empty($imgUrl)): ?>
            <a href="<?= Html::encode($imgUrl) ?>" target="_blank" rel="noopener" class="server-skin-index-card__preview-link">
                <img src="<?= Html::encode($imgUrl) ?>" alt="<?= Html::encode($model->name) ?>" class="server-skin-index-card__img" loading="lazy" width="280" height="210">
            </a>
        <?php else: ?>
            <div class="server-skin-index-card__no-photo"><?= Yii::t('common', 'Нет изображения') ?></div>
        <?php endif; ?>
    </div>
    <div class="server-skin-index-card__body">
        <div class="server-skin-index-card__name" title="<?= Html::encode($model->name) ?>"><?= Html::encode($model->name) ?></div>
        <div class="server-skin-index-card__meta">
            <?php if ($model->user): ?>
                <?= Html::a(Html::encode($model->user->username), ['/user/profile', 'userId' => $model->user->id], ['class' => 'server-skin-index-card__user']) ?>
            <?php else: ?>
                <span>—</span>
            <?php endif; ?>
            <span class="ds-badge <?= $statusBadgeClass ?>"><?= Html::encode($statusLabel) ?></span>
        </div>
        <div class="server-skin-index-card__skin-id">
            <?= Yii::t('common', 'Skin ID') ?>: <?= (int)$model->skin_id ?>
        </div>
        <div class="server-skin-index-card__date">
            <?= Yii::$app->formatter->asDate($model->created_at, 'php:Y-m-d H:i') ?>
        </div>
        <div class="server-skin-index-card__actions">
            <?php if ($model->status === ServerSkin::STATUS_WAIT): ?>
                <?= Html::a('<i class="fas fa-check"></i> ' . Yii::t('common', 'Принять'), ['success', 'id' => $model->id], ['class' => 'ds-btn ds-btn--success ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Принять скин?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?php if ($model->status !== ServerSkin::STATUS_REJECT): ?>
                <?= Html::a('<i class="fas fa-times"></i> ' . Yii::t('common', 'Отклонить'), ['reject', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Отклонить скин?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Просмотр')]) ?>
            <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Изменить')]) ?>
            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'title' => Yii::t('common', 'Удалить'), 'data' => ['confirm' => Yii::t('common', 'Удалить скин?'), 'method' => 'post']]) ?>
        </div>
    </div>
</div>

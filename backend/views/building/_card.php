<?php

use common\models\building\Building;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var Building $model */
/** @var int $index */

$images = $model->buildingImage;
$imageList = is_array($images) ? $images : (is_countable($images) ? $images : []);
if (!is_array($imageList)) {
    $imageList = iterator_to_array($imageList);
}
$cardId = 'building-card-' . $model->id;
$statusList = Building::getStatusList();
$statusLabel = ArrayHelper::getValue($statusList, $model->status, '');
$statusBadgeClass = $model->status == Building::STATUS_ACTIVE ? 'ds-badge--success' : ($model->status == Building::STATUS_WAIT ? 'ds-badge--warning' : 'ds-badge--danger');
$serverList = \common\models\servers\Servers::getServers();
$serverName = ArrayHelper::getValue($serverList, $model->server_tag, $model->server_tag);
?>
<div class="building-index-card" id="<?= $cardId ?>">
    <div class="building-index-card__gallery">
        <?php if (!empty($imageList)): ?>
            <div class="building-index-card__slides" data-current="0">
                <?php foreach ($imageList as $i => $img): ?>
                    <?php $fullUrl = $img->getPublicUrl(); ?>
                    <div class="building-index-card__slide<?= $i === 0 ? ' building-index-card__slide--active' : '' ?>" data-index="<?= $i ?>">
                        <a href="<?= Html::encode($fullUrl) ?>" target="_blank" rel="noopener" class="building-index-card__slide-link">
                            <img src="<?= Html::encode($fullUrl) ?>" alt="<?= Html::encode($model->name) ?>" class="building-index-card__img" loading="lazy" width="280" height="210">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($imageList) > 1): ?>
                <button type="button" class="building-index-card__nav building-index-card__nav--prev" aria-label="<?= Yii::t('common', 'Предыдущее фото') ?>">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="building-index-card__nav building-index-card__nav--next" aria-label="<?= Yii::t('common', 'Следующее фото') ?>">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="building-index-card__dots">
                    <?php foreach ($imageList as $i => $img): ?>
                        <button type="button" class="building-index-card__dot<?= $i === 0 ? ' building-index-card__dot--active' : '' ?>" data-index="<?= $i ?>" aria-label="<?= Yii::t('common', 'Фото {n}', ['n' => $i + 1]) ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="building-index-card__no-photo"><?= Yii::t('common', 'Нет фото') ?></div>
        <?php endif; ?>
    </div>
    <div class="building-index-card__body">
        <div class="building-index-card__name" title="<?= Html::encode($model->name) ?>"><?= Html::encode($model->name) ?></div>
        <div class="building-index-card__meta">
            <?php if ($model->user): ?>
                <?= Html::a(Html::encode($model->user->username), ['/user/profile', 'userId' => $model->user->id], ['class' => 'building-index-card__user']) ?>
            <?php else: ?>
                <span>—</span>
            <?php endif; ?>
            <span class="ds-badge <?= $statusBadgeClass ?>"><?= Html::encode($statusLabel) ?></span>
            <span class="building-index-card__server"><?= Html::encode($serverName) ?></span>
        </div>
        <div class="building-index-card__date">
            <?= Yii::$app->formatter->asDate($model->created_at, 'php:Y-m-d H:i') ?>
        </div>
        <div class="building-index-card__actions">
            <?php if ($model->status === Building::STATUS_WAIT): ?>
                <?= Html::a('<i class="fas fa-check"></i> ' . Yii::t('common', 'Принять'), ['success', 'id' => $model->id], ['class' => 'ds-btn ds-btn--success ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Принять постройку?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?php if ($model->status !== Building::STATUS_REJECT): ?>
                <?= Html::a('<i class="fas fa-times"></i> ' . Yii::t('common', 'Отклонить'), ['reject', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Отклонить постройку?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Просмотр')]) ?>
            <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Изменить')]) ?>
            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'title' => Yii::t('common', 'Удалить'), 'data' => ['confirm' => Yii::t('common', 'Удалить постройку?'), 'method' => 'post']]) ?>
        </div>
    </div>
</div>

<?php

use common\models\tasks_v2\TaskV2;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/** @var TaskV2 $model */
/** @var int $index */

$imgUrl = $model->image_path ? '/' . ltrim($model->image_path, '/') : null;
$typeList = TaskV2::getTypeList();
$checkTypeList = TaskV2::getCheckTypeList();
$typeLabel = ArrayHelper::getValue($typeList, $model->type, $model->type);
$checkTypeLabel = ArrayHelper::getValue($checkTypeList, $model->check_type, $model->check_type);
$activeBadgeClass = $model->is_active ? 'ds-badge--success' : 'ds-badge--secondary';
$rewardLine = '—';
if ($model->reward_type === TaskV2::REWARD_TYPE_CURRENCY) {
    $rewardLine = '<i class="fas fa-coins"></i> ' . number_format((float)$model->reward_amount, 0, '.', ' ');
} elseif ($model->reward_type === TaskV2::REWARD_TYPE_ITEM && $model->rewardItem) {
    try {
        $img = $model->rewardItem->imageOrig->getImagePubUrl();
        $rewardLine = '<img src="' . Html::encode($img) . '" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:4px;vertical-align:middle;"> ' . Html::encode(Yii::t('database', $model->rewardItem->name));
    } catch (\Throwable $e) {
        $rewardLine = Html::encode(Yii::t('database', $model->rewardItem->name));
    }
}
?>
<div class="tasks-v2-index-card">
    <div class="tasks-v2-index-card__preview">
        <?php if ($imgUrl): ?>
            <a href="<?= Html::encode($imgUrl) ?>" target="_blank" rel="noopener" class="tasks-v2-index-card__preview-link">
                <img src="<?= Html::encode($imgUrl) ?>" alt="<?= Html::encode($model->title) ?>" class="tasks-v2-index-card__img" loading="lazy" width="280" height="158">
            </a>
        <?php else: ?>
            <div class="tasks-v2-index-card__no-photo"><?= Yii::t('common', 'Нет изображения') ?></div>
        <?php endif; ?>
        <div class="tasks-v2-index-card__id">#<?= (int)$model->id ?></div>
    </div>
    <div class="tasks-v2-index-card__body">
        <div class="tasks-v2-index-card__title" title="<?= Html::encode($model->title) ?>"><?= Html::encode($model->title) ?></div>
        <div class="tasks-v2-index-card__meta">
            <span class="tasks-v2-index-card__type"><?= Html::encode($typeLabel) ?></span>
            <span class="tasks-v2-index-card__check"><?= Html::encode($checkTypeLabel) ?></span>
        </div>
        <div class="tasks-v2-index-card__reward"><?= $rewardLine ?></div>
        <div class="tasks-v2-index-card__stats">
            <span><?= Yii::t('common', 'Выполнено') ?>: <?= (int)$model->global_completed ?></span>
            <span><?= Yii::t('common', 'Сорт') ?>: <?= (int)$model->sort ?></span>
        </div>
        <div class="tasks-v2-index-card__badges">
            <span class="ds-badge <?= $activeBadgeClass ?>"><?= $model->is_active ? Yii::t('common', 'Активно') : Yii::t('common', 'Неактивно') ?></span>
        </div>
        <div class="tasks-v2-index-card__actions">
            <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Изменить')]) ?>
            <?= Html::a(
                '<i class="fas fa-toggle-' . ($model->is_active ? 'on' : 'off') . '"></i>',
                ['toggle-active', 'id' => $model->id],
                ['title' => $model->is_active ? Yii::t('common', 'Деактивировать') : Yii::t('common', 'Активировать'), 'class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'data' => ['method' => 'post']]
            ) ?>
            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'title' => Yii::t('common', 'Удалить'), 'data' => ['confirm' => Yii::t('common', 'Удалить задание?'), 'method' => 'post']]) ?>
        </div>
    </div>
</div>

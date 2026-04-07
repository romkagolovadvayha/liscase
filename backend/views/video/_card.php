<?php
use common\models\video\UserVideo;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
/** @var UserVideo $model */
$posterUrl = !empty($model->poster_image_150) ? $model->poster_image_150 : ($model->poster_image ?: null);
$statusList = UserVideo::getStatusList();
$statusLabel = ArrayHelper::getValue($statusList, $model->status, '');
$statusBadgeClass = $model->status == UserVideo::STATUS_ACTIVE ? 'ds-badge--success' : ($model->status == UserVideo::STATUS_WAIT ? 'ds-badge--warning' : 'ds-badge--danger');
$typeList = UserVideo::getTypeList();
$typeLabel = ArrayHelper::getValue($typeList, $model->type, $model->type);
?>
<div class="video-index-card">
    <div class="video-index-card__preview">
        <?php if (!empty($posterUrl)): ?>
            <a href="<?= Html::encode($model->video_link) ?>" target="_blank" rel="noopener" class="video-index-card__preview-link">
                <img src="<?= Html::encode($posterUrl) ?>" alt="" class="video-index-card__img" loading="lazy" width="280" height="158">
            </a>
        <?php else: ?>
            <a href="<?= Html::encode($model->video_link) ?>" target="_blank" rel="noopener" class="video-index-card__preview-link">
                <div class="video-index-card__no-photo"><?= Yii::t('common', 'Нет превью') ?></div>
            </a>
        <?php endif; ?>
    </div>
    <div class="video-index-card__body">
        <div class="video-index-card__name" title="<?= Html::encode($model->name) ?>"><?= Html::encode($model->name) ?></div>
        <div class="video-index-card__meta">
            <?php if ($model->user): ?>
                <?= Html::a(Html::encode($model->user->username), '/profile/' . $model->user->id, ['class' => 'video-index-card__user']) ?>
            <?php else: ?><span>—</span><?php endif; ?>
            <span class="ds-badge <?= $statusBadgeClass ?>"><?= Html::encode($statusLabel) ?></span>
        </div>
        <div class="video-index-card__type"><?= Html::encode($typeLabel) ?></div>
        <div class="video-index-card__date"><?= Yii::$app->formatter->asDate($model->created_at, 'php:Y-m-d H:i') ?></div>
        <div class="video-index-card__actions">
            <?php if ($model->status === UserVideo::STATUS_WAIT): ?>
                <?= Html::a('<i class="fas fa-check"></i> ' . Yii::t('common', 'Принять'), ['success', 'id' => $model->id], ['class' => 'ds-btn ds-btn--success ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Принять видео?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?php if ($model->status !== UserVideo::STATUS_REJECT): ?>
                <?= Html::a('<i class="fas fa-times"></i> ' . Yii::t('common', 'Отклонить'), ['reject', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => Yii::t('common', 'Отклонить видео?'), 'method' => 'post']]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Просмотр')]) ?>
            <?= Html::a('<i class="fas fa-pen"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm', 'title' => Yii::t('common', 'Изменить')]) ?>
            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'title' => Yii::t('common', 'Удалить'), 'data' => ['confirm' => Yii::t('common', 'Удалить видео?'), 'method' => 'post']]) ?>
        </div>
    </div>
</div>

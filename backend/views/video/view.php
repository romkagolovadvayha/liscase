<?php

use common\models\video\UserVideo;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var UserVideo $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Видео'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="video-view">
    <p>
        <?php if ($model->status !== UserVideo::STATUS_ACTIVE): ?>
            <?= Html::a(Yii::t('common', 'Принять'), ['success', 'id' => $model->id], ['class' => 'btn btn-success', 'data' => ['method' => 'post']]) ?>
        <?php endif; ?>
        <?php if ($model->status !== UserVideo::STATUS_REJECT): ?>
            <?= Html::a(Yii::t('common', 'Отклонить'), ['reject', 'id' => $model->id], ['class' => 'btn btn-danger', 'data' => ['method' => 'post']]) ?>
        <?php endif; ?>
        <?= Html::a(Yii::t('common', 'Изменить'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a(Yii::t('common', 'Удалить'), ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => ['confirm' => Yii::t('common', 'Удалить видео?'), 'method' => 'post'],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'user_id',
                'format' => 'raw',
                'value' => function (UserVideo $m) {
                    return $m->user ? Html::a(Html::encode($m->user->username), ['/user/profile', 'userId' => $m->user->id]) : '—';
                },
            ],
            'name',
            'type',
            [
                'attribute' => 'video_link',
                'format' => 'raw',
                'value' => Html::a(Html::encode($model->video_link), $model->video_link, ['target' => '_blank', 'rel' => 'noopener']),
            ],
            'poster_image:url',
            'poster_image_150:url',
            'poster_image_400:url',
            [
                'attribute' => 'status',
                'value' => \yii\helpers\ArrayHelper::getValue(UserVideo::getStatusList(), $model->status),
            ],
            'created_at',
            'updated_at',
        ],
    ]) ?>

    <?php if (!empty($model->poster_image) || !empty($model->poster_image_400)): ?>
        <div class="video-view-poster mt-3">
            <p class="text-muted"><?= Yii::t('common', 'Постер') ?>:</p>
            <a href="<?= Html::encode($model->video_link) ?>" target="_blank" rel="noopener">
                <img src="<?= Html::encode($model->poster_image_400 ?: $model->poster_image) ?>" alt="<?= Html::encode($model->name) ?>" style="max-width: 100%; height: auto; border-radius: 8px;">
            </a>
        </div>
    <?php endif; ?>
</div>

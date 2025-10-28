<?php

use common\models\radio\RadioTrack;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\radio\RadioTrack $model */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Модерация треков радио'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="radio-track-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?php if ($model->status != RadioTrack::STATUS_ACTIVE): ?>
            <?= Html::a(Yii::t('common', 'Одобрить'), ['success', 'id' => $model->id], [
                'class' => 'btn btn-success',
                'data' => [
                    'confirm' => Yii::t('common', 'Одобрить трек?'),
                    'method' => 'post',
                ],
            ]) ?>
        <?php endif; ?>
        
        <?php if ($model->status != RadioTrack::STATUS_REJECT): ?>
            <?= Html::a(Yii::t('common', 'Отклонить'), ['reject', 'id' => $model->id], [
                'class' => 'btn btn-warning',
                'data' => [
                    'confirm' => Yii::t('common', 'Отклонить трек?'),
                    'method' => 'post',
                ],
            ]) ?>
        <?php endif; ?>
        
        <?= Html::a(Yii::t('common', 'Удалить'), ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => Yii::t('common', 'Вы уверены, что хотите удалить этот трек?'),
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'radio_station_id',
                'value' => $model->radioStation->name,
            ],
            [
                'attribute' => 'user_id',
                'value' => $model->user->username,
            ],
            'title',
            'artist',
            'filename',
            [
                'attribute' => 'duration',
                'value' => $model->getFormattedDuration(),
            ],
            [
                'attribute' => 'status',
                'value' => RadioTrack::getStatusList()[$model->status] ?? '',
            ],
            'likes',
            'plays',
            'created_at:datetime',
        ],
    ]) ?>

    <?php if (file_exists($model->getFilePath())): ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?= Yii::t('common', 'Прослушать трек') ?></h3>
            </div>
            <div class="panel-body">
                <audio controls style="width: 100%;">
                    <source src="<?= Yii::getAlias('@web') . '/../../node/mode/sounds/' . $model->radioStation->folder_name . '/' . $model->filename ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </div>
    <?php endif; ?>

</div>


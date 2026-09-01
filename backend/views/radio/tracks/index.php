<?php

use common\models\radio\RadioTrack;
use yii\helpers\Html;
use backend\components\AccessibleGridView as GridView;

/** @var yii\web\View $this */
/** @var backend\models\radio\RadioTrackSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Модерация треков радио');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Радио'), 'url' => ['radio/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="radio-track-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(
            '<i class="fa fa-arrow-left"></i> ' . Yii::t('common', 'Назад к управлению радио'),
            ['radio/index'],
            ['class' => 'btn btn-default']
        ) ?>
        <?= Html::a(
            '<i class="fa fa-radio"></i> ' . Yii::t('common', 'Управление радиостанциями'),
            ['radio/stations'],
            ['class' => 'btn btn-primary']
        ) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'stationName',
                'value' => 'radioStation.name',
                'label' => Yii::t('common', 'Радиостанция'),
            ],
            [
                'attribute' => 'userName',
                'value' => 'user.username',
                'label' => Yii::t('common', 'Пользователь'),
            ],
            'title',
            'artist',
            [
                'attribute' => 'duration',
                'value' => function($model) {
                    return $model->getFormattedDuration();
                },
                'label' => Yii::t('common', 'Длительность'),
            ],
            [
                'attribute' => 'status',
                'value' => function($model) {
                    return RadioTrack::getStatusList()[$model->status] ?? '';
                },
                'filter' => RadioTrack::getStatusList(),
                'contentOptions' => function ($model) {
                    if ($model->status == RadioTrack::STATUS_WAIT) {
                        return ['style' => 'background-color: #fff3cd;'];
                    } elseif ($model->status == RadioTrack::STATUS_REJECT) {
                        return ['style' => 'background-color: #f8d7da;'];
                    } else {
                        return ['style' => 'background-color: #d4edda;'];
                    }
                },
            ],
            'likes',
            'plays',
            'created_at:datetime',

            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {success} {reject} {delete}',
                'buttons' => [
                    'success' => function ($url, $model) {
                        if ($model->status != RadioTrack::STATUS_ACTIVE) {
                            return Html::a('<span class="glyphicon glyphicon-ok"></span>', ['success', 'id' => $model->id], [
                                'title' => Yii::t('common', 'Одобрить'),
                                'data-confirm' => Yii::t('common', 'Одобрить трек?'),
                                'data-method' => 'post',
                            ]);
                        }
                        return '';
                    },
                    'reject' => function ($url, $model) {
                        if ($model->status != RadioTrack::STATUS_REJECT) {
                            return Html::a('<span class="glyphicon glyphicon-remove"></span>', ['reject', 'id' => $model->id], [
                                'title' => Yii::t('common', 'Отклонить'),
                                'data-confirm' => Yii::t('common', 'Отклонить трек?'),
                                'data-method' => 'post',
                            ]);
                        }
                        return '';
                    },
                ],
            ],
        ],
    ]); ?>

</div>


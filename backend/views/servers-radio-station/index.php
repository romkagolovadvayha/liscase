<?php

use common\models\servers\ServersRadioStation;
use backend\models\ServersRadioStationSearch;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var backend\models\ServersRadioStationSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Радиостанции серверов';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-radio-station-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Создать радиостанцию', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'name',
            [
                'attribute' => 'url',
                'format' => 'raw',
                'value' => function($model) {
                    return Html::a(Html::encode(mb_substr($model->url, 0, 50) . '...'), $model->url, ['target' => '_blank']);
                },
                'contentOptions' => ['style' => 'max-width: 300px;'],
            ],
            [
                'attribute' => 'logo',
                'format' => 'raw',
                'value' => function($model) {
                    if ($model->logo) {
                        return Html::img($model->getLogoUrl(), ['style' => 'max-width: 50px; max-height: 50px;']);
                    }
                    return '<span class="text-muted">Нет логотипа</span>';
                },
            ],
            'sort',
            [
                'attribute' => 'status',
                'format' => 'raw',
                'value' => function($model) {
                    return $model->status == ServersRadioStation::STATUS_ACTIVE 
                        ? '<span class="badge badge-success">Активна</span>'
                        : '<span class="badge badge-secondary">Неактивна</span>';
                },
                'filter' => ServersRadioStation::getStatusList(),
            ],
            'created_at:datetime',

            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, ServersRadioStation $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>


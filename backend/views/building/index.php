<?php

use common\models\building\Building;
use yii\helpers\Html;
use kartik\grid\GridView;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\models\building\BuildingSearch;

/** @var yii\web\View $this */
/** @var backend\models\building\BuildingSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Постройки';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="building-index">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '60'],
            ],
            [
                'attribute' => 'user_id',
                'options'   => ['width' => '150'],
                'format'    => 'raw',
                'value'          => function (Building $model) {
                    return "<a href=\"/user/profile?userId={$model->user->id}\">{$model->user->username}</a>";
                },
            ],
            'name',
            [
                'attribute'       => 'status',
                'options'   => ['width' => '140'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], Building::getStatusList()),
                'value'           => function (Building $model) {
                    $statusList = Building::getStatusList();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                },
            ],
            [
                'attribute'       => 'server_tag',
                'options'   => ['width' => '180'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], \common\models\servers\Servers::getServers()),
                'value'           => function (Building $model) {
                    $statusList = \common\models\servers\Servers::getServers();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->server_tag);
                },
            ],
            [
                'options'   => ['width' => '200'],
                'class' => \common\components\grid\DateColumn::class,
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Building $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

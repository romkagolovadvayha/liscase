<?php

use common\models\serverskin\ServerSkin;
use yii\helpers\Html;
use kartik\grid\GridView;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\models\serverskin\ServerSkinSearch;

/** @var yii\web\View $this */
/** @var ServerSkinSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title =  Yii::t('common', 'Свои скины');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ServerSkin-index">
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
                'value'          => function (ServerSkin $model) {
                    return "<a href=\"/user/profile?userId={$model->user->id}\">{$model->user->username}</a>";
                },
            ],
            'name',
            'skin_id',
            [
                'attribute'       => 'status',
                'options'   => ['width' => '140'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], ServerSkin::getStatusList()),
                'value'           => function (ServerSkin $model) {
                    $statusList = ServerSkin::getStatusList();
                    return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                },
            ],
            [
                'options'   => ['width' => '200'],
                'class' => \common\components\grid\DateColumn::class,
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, ServerSkin $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

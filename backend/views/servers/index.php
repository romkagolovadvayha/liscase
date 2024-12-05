<?php

use common\models\servers\Servers;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var backend\models\ServersSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Сервера';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-index">
    <p>
        <?= Html::a('Новый сервер', ['create'], ['class' => 'btn btn-success']) ?> <?= Html::a(Yii::t('common', 'Сортировать'),
                                                                                               ['sort'],
                                                                                               ['class' => 'btn btn-primary']); ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'format'    => 'raw',
                'options'   => ['width' => '40'],
            ],
            'name:ntext',
            [
                'attribute'       => 'wipe',
                'options'   => ['width' => '200'],
                'value'     => function (Servers $model) {
                    return $model->wipe;
                },
            ],
            [
                'attribute'       => 'next_wipe',
                'options'   => ['width' => '200'],
                'value'     => function (Servers $model) {
                    return $model->next_wipe;
                },
            ],
            [
                'attribute'       => 'global_wipe',
                'options'   => ['width' => '200'],
                'value'     => function (Servers $model) {
                    return $model->global_wipe;
                },
            ],
            [
                'attribute'       => 'updated_at',
                'options'   => ['width' => '200'],
                'value'     => function (Servers $model) {
                    return $model->updated_at;
                },
            ],
            [
                'attribute' => 'status',
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Все'], Servers::getStatusList()),
                'options'   => ['width' => '100'],
                'value'     => function (Servers $model) {
                    return ArrayHelper::getValue(Servers::getStatusList(), $model->status);
                },
            ],
            [
                'class' => ActionColumn::className(),
                'options'   => ['width' => '40'],
                'template' => '{update}',
                'urlCreator' => function ($action, Servers $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

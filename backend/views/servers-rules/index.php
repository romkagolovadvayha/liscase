<?php

use common\models\servers\ServersRules;
use backend\models\ServersRulesSearch;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var backend\models\ServersRulesSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Правила серверов';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-rules-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Создать правило', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'category_id',
                'value' => function($model) {
                    return $model->category ? $model->category->name : '';
                },
                'filter' => \yii\helpers\ArrayHelper::map(
                    \common\models\servers\ServersRulesCategory::find()->all(),
                    'id',
                    'name'
                ),
            ],
            [
                'attribute' => 'server_id',
                'label' => 'Серверы',
                'format' => 'raw',
                'value' => function($model) {
                    $servers = $model->servers;
                    if (empty($servers)) {
                        return '<span class="badge badge-info">Общее</span>';
                    }
                    $serverNames = [];
                    foreach ($servers as $server) {
                        $serverNames[] = '<span class="badge badge-secondary">' . Html::encode($server->name) . '</span>';
                    }
                    return implode(' ', $serverNames);
                },
                'filter' => \yii\helpers\ArrayHelper::map(
                    \common\models\servers\Servers::find()->all(),
                    'id',
                    'name'
                ),
            ],
            [
                'attribute' => 'title',
                'format' => 'ntext',
                'contentOptions' => ['style' => 'max-width: 200px;'],
            ],
            [
                'attribute' => 'content',
                'format' => 'html',
                'value' => function($model) {
                    return mb_substr(strip_tags($model->content), 0, 100) . '...';
                },
                'contentOptions' => ['style' => 'max-width: 300px;'],
            ],
            [
                'attribute' => 'punishment',
                'format' => 'raw',
                'value' => function($model) {
                    return $model->punishment ? '<span class="badge badge-warning">' . Html::encode($model->punishment) . '</span>' : '';
                },
            ],
            'sort',
            'created_at:datetime',

            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, ServersRules $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>


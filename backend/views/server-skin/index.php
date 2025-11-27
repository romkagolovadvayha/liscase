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

$this->title = Yii::t('common', 'Свои скины');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="server-skin-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
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
                    $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->user->id]);
                    return Html::a(Html::encode($model->user->username), $url, [
                        'class' => 'ds-text--primary',
                        'style' => 'text-decoration: none;'
                    ]);
                },
            ],
            'name',
            'skin_id',
            [
                'attribute'       => 'status',
                'options'   => ['width' => '140'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], ServerSkin::getStatusList()),
                'format'    => 'raw',
                'value'           => function (ServerSkin $model) {
                    $statusList = ServerSkin::getStatusList();
                    $status = \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                    $badgeClass = $model->status == ServerSkin::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                    return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
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
    </div>
</div>

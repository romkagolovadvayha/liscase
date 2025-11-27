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
<div class="servers-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ds-flex ds-flex--gap-md">
                <?= Html::a('<i class="fas fa-plus"></i> Новый сервер', ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
                <?= Html::a('<i class="fas fa-sort"></i> ' . Yii::t('common', 'Сортировать'), ['sort'], ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
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
                    if ($model->status != Servers::STATUS_ACTIVE) {
                        return '';
                    }
                    return time() - strtotime($model->updated_at) . " сек. назад";
                },
            ],
            [
                'attribute' => 'status',
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Все'], Servers::getStatusList()),
                'options'   => ['width' => '100'],
                'format'    => 'raw',
                'value'     => function (Servers $model) {
                    $status = ArrayHelper::getValue(Servers::getStatusList(), $model->status);
                    $badgeClass = $model->status == Servers::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                    return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
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
    </div>
</div>

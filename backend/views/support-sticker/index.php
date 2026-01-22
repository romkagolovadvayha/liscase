<?php

use common\models\support\SupportSticker;
use yii\helpers\Html;
use kartik\grid\GridView;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\models\support\SupportStickerSearch;

/** @var yii\web\View $this */
/** @var SupportStickerSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Стикеры поддержки');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="support-sticker-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a(Yii::t('common', 'Создать стикер'), ['create'], ['class' => 'btn btn-success']) ?>
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
                'attribute' => 'code',
                'options'   => ['width' => '150'],
            ],
            'name',
            [
                'attribute' => 'type',
                'options'   => ['width' => '120'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], SupportSticker::getTypeList()),
                'format'    => 'raw',
                'value'           => function (SupportSticker $model) {
                    $typeList = SupportSticker::getTypeList();
                    $type = \yii\helpers\ArrayHelper::getValue($typeList, $model->type);
                    return Html::encode($type);
                },
            ],
            [
                'attribute' => 'file',
                'options'   => ['width' => '200'],
                'format'    => 'raw',
                'value'          => function (SupportSticker $model) {
                    if ($model->type === SupportSticker::TYPE_IMAGE) {
                        return Html::img($model->getPublicUrl(), ['style' => 'max-width: 100px; max-height: 100px;']);
                    } else {
                        return Html::tag('video', '', [
                            'src' => $model->getPublicUrl(),
                            'style' => 'max-width: 100px; max-height: 100px;',
                            'controls' => true
                        ]);
                    }
                },
            ],
            [
                'attribute' => 'status',
                'options'   => ['width' => '140'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Любой'], SupportSticker::getStatusList()),
                'format'    => 'raw',
                'value'           => function (SupportSticker $model) {
                    $statusList = SupportSticker::getStatusList();
                    $status = \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                    $badgeClass = $model->status == SupportSticker::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                    return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                },
            ],
            [
                'attribute' => 'sort',
                'options'   => ['width' => '100'],
            ],
            [
                'options'   => ['width' => '200'],
                'class' => \common\components\grid\DateColumn::class,
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, SupportSticker $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>
        </div>
    </div>
</div>










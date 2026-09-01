<?php

use backend\models\ClanSearch;
use common\models\clan\Clan;
use common\models\servers\Servers;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/** @var ClanSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Кланы');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

$privacyFilter = [
    '' => Yii::t('common', 'Все'),
    Clan::PRIVACY_OPEN => Yii::t('common', 'Открытый'),
    Clan::PRIVACY_CLOSED => Yii::t('common', 'Закрытый'),
    Clan::PRIVACY_INVITE_ONLY => Yii::t('common', 'По приглашению'),
];

$serverList = ['' => Yii::t('common', 'Все серверы')] + ArrayHelper::map(
    Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
    'id',
    'name'
);
?>

<div class="clan-index-page w-full">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
        'options' => ['class' => 'admin-grid-view-dark'],
        'layout' => "{items}\n{pager}",
        'filterRowOptions' => ['style' => 'display: none;'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'columns' => [
            [
                'attribute' => 'id',
                'options' => ['width' => '72'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'name',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'format' => 'raw',
                'value' => function (Clan $model) {
                    return Html::a(Html::encode($model->name), ['view', 'id' => $model->id], ['class' => 'text-white hover:underline']);
                },
            ],
            [
                'attribute' => 'tag',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'server_id',
                'label' => Yii::t('common', 'Сервер'),
                'filter' => $serverList,
                'filterType' => GridView::FILTER_SELECT2,
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => function (Clan $model) {
                    return $model->server ? Html::encode($model->server->name) : '—';
                },
            ],
            [
                'attribute' => 'leader_username',
                'label' => Yii::t('common', 'Лидер'),
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => function (Clan $model) {
                    return $model->leaderUser ? Html::encode($model->leaderUser->username) : '—';
                },
            ],
            [
                'attribute' => 'privacy',
                'filter' => $privacyFilter,
                'filterType' => GridView::FILTER_SELECT2,
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => function (Clan $model) {
                    return Html::encode($model->getPrivacyLabel());
                },
            ],
            [
                'attribute' => 'level',
                'options' => ['width' => '80'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'label' => Yii::t('common', 'Участников'),
                'options' => ['width' => '100'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => function (Clan $model) {
                    return (int)$model->activeMembersCount;
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:Y-m-d H:i'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update}',
                'urlCreator' => function ($action, Clan $model) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                },
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
        ],
    ]) ?>
</div>

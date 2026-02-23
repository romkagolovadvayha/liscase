<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use backend\models\TelegramConstructor;
use backend\models\AudienceSearch;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\user\User;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $audienceId */
/** @var $audienceCount int */
/** @var $audience array */

$this->title = 'Аудитория: ' . ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $audienceId);
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor/index']];
$this->params['breadcrumbs'][] = $this->title;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="tc-audience-page w-full">
    <div class="p-4 lg:p-6 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-semibold text-white"><?= Yii::$app->formatter->asInteger($audienceCount) ?></span>
            <span class="text-sm text-gray-400">Всего получателей</span>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel'  => $searchModel,
        'tableOptions' => ['class' => 'table-auto w-full text-sm'],
        'options'      => ['class' => 'admin-grid-view-dark tc-audience-grid'],
        'layout'       => "{items}\n{pager}",
        'filterRowOptions' => ['style' => 'display: none;'],
        'bordered'     => false,
        'striped'      => false,
        'hover'        => true,
        'columns'      => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '80'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'username',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (AudienceSearch $model) {
                    $url = Url::to(['/user/profile', 'userId' => $model->id]);
                    return Html::a(Html::encode($model->username), $url, ['class' => 'text-white hover:underline', 'style' => 'text-decoration: none;']);
                },
            ],
            [
                'attribute' => 'steam_id',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (AudienceSearch $model) {
                    $url = Url::to(['/user/profile', 'userId' => $model->id]);
                    return Html::a(Html::encode($model->steam_id), $url, ['class' => 'text-white hover:underline', 'style' => 'text-decoration: none;']);
                },
            ],
            [
                'attribute' => 'ref_code',
                'label'     => 'Реф.код',
                'format'    => 'raw',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (AudienceSearch $model) {
                    $url = Url::to(['/user/profile', 'userId' => $model->id]);
                    return Html::a(Html::encode($model->ref_code), $url, ['class' => 'text-white hover:underline', 'style' => 'text-decoration: none;']);
                },
            ],
            [
                'attribute' => 'status',
                'filter'    => User::getStatusList(),
                'filterType' => GridView::FILTER_SELECT2,
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value'     => function (AudienceSearch $model) {
                    return ArrayHelper::getValue(User::getStatusList(), $model->status);
                },
            ],
            [
                'attribute' => 'created_at',
                'class'     => \common\components\grid\DateColumn::class,
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
        ],
    ]); ?>
</div>

<style>
.tc-audience-grid { background: hsl(0 0% 10% / 1) !important; }
.tc-audience-grid .table, .tc-audience-grid table, .tc-audience-grid .kv-grid-table { background: hsl(0 0% 10% / 1) !important; border-collapse: collapse; color: white !important; border: none !important; }
.tc-audience-grid .table thead th, .tc-audience-grid table thead th { background: hsl(0 0% 20.4% / 1) !important; color: hsl(0 0% 70% / 1) !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-audience-grid .table tbody td, .tc-audience-grid table tbody td { background: transparent !important; color: white !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-audience-grid .table tbody tr:hover { background: hsl(0 0% 15% / 1) !important; }
.tc-audience-grid .pagination, .tc-audience-grid .kv-panel-pager { background: hsl(0 0% 10% / 1) !important; color: white !important; }
.tc-audience-grid .pagination .page-link { background: hsl(0 0% 20.4% / 1) !important; color: white !important; border-color: hsl(0 0% 15.3% / 1) !important; }
</style>

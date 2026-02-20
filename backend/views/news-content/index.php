<?php

use yii\helpers\Url;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\grid\ActionColumn;
use common\models\news\NewsContentSearch;

/** @var \common\models\news\News $news */
$news = $this->context->getNews();

$this->title = $news->name;
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
$this->params['breadcrumbs'][] = ['label' => 'Список новостей', 'url' => Url::toRoute(['/news/index'])];
$this->params['breadcrumbs'][] = ['label' => $this->title];

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="news-content-index-page w-full">
    <div class="w-full">
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
                    'attribute' => 'language',
                    'filter' => $searchModel->getLanguageList(),
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (NewsContentSearch $model) {
                        return ArrayHelper::getValue($model->getLanguageList(), $model->language);
                    },
                ],
                ['attribute' => 'title', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'created_at',
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update}',
                    'options' => ['width' => '45'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'buttons' => [
                        'update' => function ($url, $model) {
                            $url = $this->context->prepareUrl('update', ['id' => $model->id]);
                            return \common\components\grid\ManageButton::update($url);
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>
</div>

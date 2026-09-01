<?php

use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\grid\ActionColumn;
use common\models\news\NewsSearch;

$this->title = Yii::t('common', 'Новости');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="news-index-page w-full">
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
                ['attribute' => 'id', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'name', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'status',
                    'filter' => NewsSearch::getStatusList(),
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (NewsSearch $model) {
                        if ($model->status == NewsSearch::STATUS_PREPARE) {
                            if (!$model->getContentModel('ru-RU')) {
                                $publicBtn = Html::tag('span', 'Публикация невозможна', ['class' => 'text-red-400']);
                            } else {
                                $publicBtn = Html::a('Опубликовать', ['/news/publish', 'id' => $model->id], [
                                    'class' => 'ds-btn ds-btn--primary ds-btn--sm',
                                    'data-confirm' => 'Вы уверены, что хотите опубликовать эту новость?',
                                ]);
                            }
                        } else {
                            $publicBtn = Html::a('Снять с публикации', ['/news/prepare', 'id' => $model->id], [
                                'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                'data-confirm' => 'Вы уверены, что хотите снять с публикации эту новость?',
                            ]);
                        }
                        return ArrayHelper::getValue(NewsSearch::getStatusList(), $model->status) . '<br />' . $publicBtn;
                    },
                ],
                [
                    'label' => 'Переводы на языки',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (NewsSearch $model) {
                        $languages = [];
                        foreach ($model->newsContents as $newsContent) {
                            $languages[] = $newsContent->language;
                        }
                        $settingBtn = Html::a('Перейти к настройке', ['/news-content/index', 'newsId' => $model->id], ['class' => 'text-white hover:underline']);
                        return !empty($languages) ? 'Переводов: ' . count($languages) . '<br />(' . implode(', ', $languages) . ')<br />' . $settingBtn : $settingBtn;
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'date_published',
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update} {delete}',
                    'options' => ['width' => '90'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                    'buttons' => [
                        'update' => function ($url, $model) {
                            return \common\components\grid\ManageButton::update($url);
                        },
                        'delete' => function ($url, $model) {
                            return \common\components\grid\ManageButton::delete($url);
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>
</div>

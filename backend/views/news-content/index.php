<?php

use yii\helpers\Url;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\news\NewsContentSearch;

/** @var \common\models\news\News $news */
$news = $this->context->getNews();

$this->title = $news->name;

$this->params['breadcrumbs'][] = [
    'label' => 'Список новостей',
    'url'   => Url::toRoute(['/news/index']),
];

$this->params['breadcrumbs'][] = [
    'label' => $this->title,
];
?>

<?= Html::a('Добавить перевод новости',
    $this->context->prepareUrl('create'),
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'attribute' => 'language',
            'filter'    => $searchModel->getLanguageList(),
            'value'     => function (NewsContentSearch $model) {
                return \yii\helpers\ArrayHelper::getValue($model->getLanguageList(), $model->language);
            },
        ],
        'title',
        [
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update}',
            'options'  => ['width' => '45'],
            'buttons'  => [
                'update' => function ($url, $model) {
                    $url = $this->context->prepareUrl('update', ['id' => $model->id]);

                    return \common\components\grid\ManageButton::update($url);
                },
            ],
        ],
    ],
]);
?>

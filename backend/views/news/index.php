<?php

use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\news\NewsSearch;

$this->title = Yii::t('common', 'Новости');
?>

<?= Html::a(' Создать новость',
    '/news/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        'id',
        'name',
        [
            'attribute' => 'status',
            'filter'    => NewsSearch::getStatusList(),
            'format'    => 'raw',
            'value'     => function (NewsSearch $model) {
                if ($model->status == NewsSearch::STATUS_PREPARE) {
                    if (!$model->getContentModel('ru-RU')) {
                        $publicBtn = Html::tag('span', 'Публикация невозможна', [
                            'class' => 'text-danger',
                        ]);
                    } else {
                        $publicBtn = Html::a('Опубликовать', ['/news/publish', 'id' => $model->id], [
                            'class'        => 'btn btn-sm btn-info',
                            'data-confirm' => 'Вы уверены, что хотите опубликовать эту новость?',
                        ]);
                    }


                } else {
                    $publicBtn = Html::a('Снять с публикации', ['/news/prepare', 'id' => $model->id], [
                        'class'        => 'btn btn-sm btn-danger',
                        'data-confirm' => 'Вы уверены, что хотите снять с публикации эту новость?',
                    ]);
                }

                return \yii\helpers\ArrayHelper::getValue(NewsSearch::getStatusList(), $model->status)
                       . '<br />' . $publicBtn;
            },
        ],
        [
            'label' => 'Переводы на языки',
            'format' => 'raw',
            'value' => function (NewsSearch $model) {
                $languages = [];
                foreach ($model->newsContents as $newsContent) {
                    $languages[] = $newsContent->language;
                }

                $settingBtn = Html::a('Перейти к настройке',
                    ['/news-content/index', 'newsId' => $model->id],
                    ['class' => 'btn btn-sm btn-primary']
                );

                return !empty($languages)
                    ? 'Переводов: ' . count($languages) . '<br />(' . implode(', ', $languages) . ')<br />' . $settingBtn
                    : $settingBtn;
            },
        ],
        [
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'     => \common\components\grid\DateColumn::class,
            'attribute' => 'date_published',
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update} {delete}',
            'options'  => ['width' => '90'],
            'buttons'  => [
                'update' => function ($url, $model) {
                    return \common\components\grid\ManageButton::update($url);
                },
                'delete' => function ($url, $model) {
                    return \common\components\grid\ManageButton::delete($url);
                },
            ],
        ],
    ],
]);
?>

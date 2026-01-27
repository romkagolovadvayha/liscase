<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use backend\models\TelegramConstructorMessage;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\TelegramConstructorMessageSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Сообщения для рассылок';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <div class="ds-flex ds-items-center ds-justify-center ds-gap-md">
                <?= Html::a(
                    '<i class="bi bi-plus-circle"></i> Добавить',
                    ['create'],
                    ['class' => 'ds-btn ds-btn--success']
                ) ?>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Сообщения</h5>
        </div>
        <div class="ds-card__body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'layout' => "{items} {pager}",
                'columns' => [
                    [
                        'attribute' => 'id',
                        'options'   => ['width' => '50'],
                    ],
                    [
                        'attribute' => 'image_link',
                        'options'   => ['width' => '60'],
                        'label' => '',
                        'filter'    => false,
                        'format' => 'raw',
                        'value'     => function (TelegramConstructorMessage $model) {
                            return Html::img($model->getPubUrl(), [
                                'width' => '50px',
                                'height' => '50px',
                                'style' => 'object-fit: cover; border-radius: 4px;',
                                'loading' => 'lazy',
                                'alt' => 'Preview'
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'title',
                        'format' => 'raw',
                        'value' => function ($model) {
                            return Html::a(
                                Html::encode($model->title),
                                ['/telegram-constructor-message/view', 'id' => $model->id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'class' => \common\components\grid\DateColumn::class,
                    ],
                    [
                        'class'    => 'yii\grid\ActionColumn',
                        'template' => '{update} {delete}',
                        'options'  => [
                            'width' => '90'
                        ],
                        'buttons'  => [
                            'update' => function ($url, $model) {
                                return Html::a(
                                    '<i class="bi bi-pencil"></i>',
                                    $url,
                                    ['class' => 'ds-btn ds-btn--primary ds-btn--sm', 'title' => 'Редактировать']
                                );
                            },
                            'delete' => function ($url, $model) {
                                return Html::a(
                                    '<i class="bi bi-trash"></i>',
                                    $url,
                                    [
                                        'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                        'title' => 'Удалить',
                                        'data' => [
                                            'confirm' => 'Вы уверены, что хотите удалить это сообщение?',
                                            'method' => 'post',
                                        ],
                                    ]
                                );
                            },
                        ],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

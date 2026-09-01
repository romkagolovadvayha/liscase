<?php

use backend\models\TelegramRecipients;
use backend\components\AccessibleKartikGridView as GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model TelegramRecipients */

$this->title = Yii::t('common', 'Список получателей сообщений телеграм бота');
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
                    '<i class="bi bi-plus-circle"></i> ' . Yii::t('common', 'Создать новый список'),
                    '/telegram-recipients/create',
                    ['class' => 'ds-btn ds-btn--success']
                ); ?>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Списки получателей</h5>
        </div>
        <div class="ds-card__body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel'  => $searchModel,
                'layout' => "{items} {pager}",
                'columns'      => [
                    [
                        'attribute' => 'id',
                        'headerOptions' => ['style' => 'width:5%'],
                    ],
                    [
                        'attribute' => 'ref_id',
                        'filter'    => false,
                        'headerOptions' => ['style' => 'width:45%'],
                        'format' => 'raw',
                        'value' => function ($model) {
                            if (is_string($model->ref_id)) {
                                $refIds = json_decode($model->ref_id, true);
                            } else {
                                $refIds = $model->ref_id;
                            }
                            if (is_array($refIds) && !empty($refIds)) {
                                return Html::tag('span', count($refIds) . ' получателей', ['class' => 'ds-badge ds-badge--info']);
                            }
                            return Html::tag('span', 'Нет получателей', ['class' => 'ds-badge ds-badge--primary']);
                        },
                    ],
                    [
                        'attribute' => 'name',
                        'filter'    => true,
                        'headerOptions' => ['style' => 'width:20%'],
                        'format' => 'raw',
                        'value' => function ($model) {
                            return Html::a(
                                Html::encode($model->name),
                                ['/telegram-recipients/view', 'id' => $model->id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute' => 'quantity',
                        'filter'    => true,
                        'headerOptions' => ['style' => 'width:10%'],
                        'format' => 'raw',
                        'value' => function ($model) {
                            return Html::tag('span', number_format($model->quantity, 0, '.', ' '), ['class' => 'ds-badge ds-badge--primary']);
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
                                            'confirm' => 'Вы уверены, что хотите удалить этот список?',
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

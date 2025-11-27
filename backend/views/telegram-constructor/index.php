<?php

use backend\models\TelegramConstructor;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\components\helpers\Role;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $countTelegramUsers */
/** @var $countVkUsers */
/** @var $model TelegramConstructor */

$this->title = Yii::t('common', 'Конструктор сообщений телеграм бота');
?>

<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <!-- Статистика аудитории -->
    <div class="ds-card mb-4">
        <h2 class="mb-4">Статистика аудитории</h2>
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value">
                        <?= Yii::$app->formatter->asInteger($countTelegramUsers); ?>
                        <?= Html::a(
                            '<i class="bi bi-arrow-clockwise"></i>',
                            '/telegram-constructor/update-telegram-audience',
                            [
                                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
                                'title' => 'Обновить счетчик Telegram получателей',
                                'style' => 'margin-left: 0.5rem;',
                                'data' => [
                                    'confirm' => 'Вы уверены, что хотите обновить счетчик Telegram получателей?',
                                    'method' => 'post',
                                ],
                            ]
                        ); ?>
                    </div>
                    <div class="ds-counter__label">Telegram получателей</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ds-counter">
                    <div class="ds-counter__value">
                        <?= Yii::$app->formatter->asInteger($countVkUsers); ?>
                        <?= Html::a(
                            '<i class="bi bi-arrow-clockwise"></i>',
                            '/telegram-constructor/update-vk-audience',
                            [
                                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
                                'title' => 'Обновить аудиторию ВКонтакте',
                                'style' => 'margin-left: 0.5rem;',
                                'data' => [
                                    'confirm' => 'Вы уверены, что хотите обновить аудиторию ВКонтакте? Это может занять некоторое время.',
                                    'method' => 'post',
                                ],
                            ]
                        ); ?>
                    </div>
                    <div class="ds-counter__label">ВКонтакте получателей</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Действия -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <div class="ds-flex ds-items-center ds-justify-center ds-gap-md">
                <?= Html::a(
                    '<i class="bi bi-plus-circle"></i> ' . Yii::t('common', 'Создать новую рассылку'),
                    '/telegram-constructor/create',
                    ['class' => 'ds-btn ds-btn--success']
                ); ?>
                <?= Html::a(
                    '<i class="bi bi-envelope"></i> ' . Yii::t('common', 'Сообщения для рассылок'),
                    '/telegram-constructor-message',
                    ['class' => 'ds-btn ds-btn--info']
                ); ?>
            </div>
        </div>
    </div>

    <!-- Таблица рассылок -->
    <div class="ds-card">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Рассылки</h5>
        </div>
        <div class="ds-card__body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel'  => $searchModel,
                'layout'       => "{items} {pager}",
                'columns'      => [
                    [
                        'attribute' => 'id',
                        'options'  => [
                            'width' => '80'
                        ],
                    ],
                    [
                        'attribute' => 'title',
                        'format' => 'raw',
                        'value' => function ($model) {
                            return Html::a(
                                Html::encode($model->title),
                                ['/telegram-constructor/view', 'id' => $model->id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute' => 'bot_id',
                        'filter'    => TelegramConstructor::getBotList(),
                        'format' => 'raw',
                        'value'     => function ($model) {
                            $botName = ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id);
                            $badgeClass = $model->bot_id == TelegramConstructor::VK_GROUP ? 'ds-badge--info' : 'ds-badge--primary';
                            return Html::tag('span', Html::encode($botName), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'attribute' => 'audience_id',
                        'filter'    => TelegramConstructor::getAudienceList(),
                        'format' => 'raw',
                        'value'     => function ($model) {
                            $audienceName = ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $model->audience_id);
                            return Html::tag('span', Html::encode($audienceName), ['class' => 'ds-badge ds-badge--primary']);
                        },
                    ],
                    [
                        'attribute' => 'message',
                        'label' => 'Сообщение',
                        'format' => 'raw',
                        'value'     => function (\backend\models\TelegramConstructorSearch $model) {
                            if (empty($model->telegramConstructorMessage)) {
                                return Html::tag('span', 'Удалено', ['class' => 'ds-badge ds-badge--danger']);
                            }
                            return Html::a(
                                Html::encode($model->telegramConstructorMessage->title),
                                ['/telegram-constructor-message/update', 'id' => $model->telegramConstructorMessage->id],
                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                            );
                        },
                    ],
                    [
                        'attribute'      => 'status',
                        'filter'         => \kartik\select2\Select2::widget([
                            'model'         => $searchModel,
                            'attribute'     => 'status',
                            'data'          => ArrayHelper::merge(['all' => 'Все'], TelegramConstructor::getStatusList()),
                            'options'       => [
                                'class'       => 'form-control',
                                'placeholder' => '...',
                            ],
                            'pluginOptions' => [
                                'allowClear'    => true,
                                'selectOnClose' => true,
                            ],
                        ]),
                        'contentOptions' => [
                            'width' => 120,
                        ],
                        'format' => 'raw',
                        'value'          => function ($model) {
                            $statusName = ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status);
                            $badgeClass = 'ds-badge--primary';
                            if ($model->status == TelegramConstructor::STATUS_SUCCESS) {
                                $badgeClass = 'ds-badge--success';
                            } elseif ($model->status == TelegramConstructor::STATUS_ERROR) {
                                $badgeClass = 'ds-badge--danger';
                            } elseif ($model->status == TelegramConstructor::STATUS_IN_PROGRESS) {
                                $badgeClass = 'ds-badge--warning';
                            }
                            return Html::tag('span', Html::encode($statusName), ['class' => 'ds-badge ' . $badgeClass]);
                        },
                    ],
                    [
                        'class' => \common\components\grid\DateColumn::class,
                    ],
                    [
                        'class'    => 'yii\grid\ActionColumn',
                        'template' => '{play} {update} {delete}',
                        'options'  => [
                            'width' => '130'
                        ],
                        'buttons'  => [
                            'play' => function ($url, $model) {
                                if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                    return '';
                                }
                                $user = Yii::$app->user->identity;
                                if (!$user || !method_exists($user, 'canRoles') || !$user->canRoles([Role::ROLE_ADMIN])) {
                                    return Html::tag('span', Html::a('<i class="bi bi-play"></i>', '#', [
                                        'class' => 'ds-btn ds-btn--primary ds-btn--sm disabled',
                                    ]), [
                                        'title' => Yii::t('common', 'Недостаточно прав для использования')
                                    ]);
                                }
                                return Html::a(
                                    '<i class="bi bi-play"></i>',
                                    $url,
                                    [
                                        'class' => 'ds-btn ds-btn--success ds-btn--sm',
                                        'title' => 'Запустить рассылку',
                                        'data' => [
                                            'confirm' => 'Вы уверены, что хотите запустить рассылку?',
                                            'method' => 'post',
                                        ],
                                    ]
                                );
                            },
                            'update' => function ($url, $model) {
                                if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                    return '';
                                }
                                return Html::a(
                                    '<i class="bi bi-pencil"></i>',
                                    $url,
                                    ['class' => 'ds-btn ds-btn--primary ds-btn--sm', 'title' => 'Редактировать']
                                );
                            },
                            'delete' => function ($url, $model) {
                                if ($model->status !== TelegramConstructor::STATUS_NEW) {
                                    return '';
                                }
                                $user = Yii::$app->user->identity;
                                if (!$user || !method_exists($user, 'canRoles') || !$user->canRoles([Role::ROLE_ADMIN])) {
                                    return Html::tag('span', Html::a('<i class="bi bi-trash"></i>', '#', [
                                        'class' => 'ds-btn ds-btn--primary ds-btn--sm disabled',
                                    ]), [
                                        'title' => Yii::t('common', 'Недостаточно прав для использования')
                                    ]);
                                }
                                return Html::a(
                                    '<i class="bi bi-trash"></i>',
                                    $url,
                                    [
                                        'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                        'title' => 'Удалить',
                                        'data' => [
                                            'confirm' => 'Вы уверены, что хотите удалить эту рассылку?',
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

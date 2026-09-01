<?php

use backend\models\Notification;
use backend\components\AccessibleGridView as GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;
use yii\data\ActiveDataProvider;

/** @var yii\web\View $this */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Управление уведомлениями';
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="notifications-index">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-bell"></i> Уведомления
                    </h3>
                    <div class="box-tools pull-right">
                        <?= Html::a(
                            '<i class="fa fa-plus"></i> Создать уведомление',
                            ['/notification/create'],
                            ['class' => 'btn btn-success btn-sm']
                        ) ?>
                        <?= Html::a(
                            '<i class="fa fa-send"></i> Отправить всем',
                            ['/notification/send-to-all'],
                            ['class' => 'btn btn-primary btn-sm']
                        ) ?>
                        <?= Html::a(
                            '<i class="fa fa-trash"></i> Очистить истекшие',
                            ['/notification/clean-expired'],
                            [
                                'class' => 'btn btn-warning btn-sm',
                                'data' => [
                                    'confirm' => 'Вы уверены, что хотите удалить все истекшие уведомления?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
                </div>
                <div class="box-body">
                    <?php Pjax::begin(); ?>
                    
                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'filterModel' => $searchModel,
                        'columns' => [
                            ['class' => 'yii\grid\SerialColumn'],
                            
                            [
                                'attribute' => 'title',
                                'format' => 'html',
                                'value' => function ($model) {
                                    return Html::a(
                                        Html::encode($model->title),
                                        ['/notification/view', 'id' => $model->id],
                                        ['title' => 'Просмотр уведомления']
                                    );
                                },
                            ],
                            
                            [
                                'attribute' => 'type',
                                'format' => 'html',
                                'value' => function ($model) {
                                    $types = [
                                        'info' => '<span class="label label-info">Информация</span>',
                                        'success' => '<span class="label label-success">Успех</span>',
                                        'warning' => '<span class="label label-warning">Предупреждение</span>',
                                        'error' => '<span class="label label-danger">Ошибка</span>',
                                        'support' => '<span class="label label-primary">Поддержка</span>',
                                        'announcement' => '<span class="label label-purple">Объявление</span>',
                                        'system' => '<span class="label label-default">Система</span>',
                                        'promotion' => '<span class="label label-pink">Акция</span>',
                                        'server_wipe' => '<span class="label label-orange">Вайп сервера</span>',
                                        'maintenance' => '<span class="label label-yellow">Техобслуживание</span>',
                                    ];
                                    return $types[$model->type] ?? '<span class="label label-default">' . $model->type . '</span>';
                                },
                                'filter' => Notification::getTypeLabels(),
                            ],
                            
                            [
                                'attribute' => 'user_id',
                                'format' => 'html',
                                'value' => function ($model) {
                                    if ($model->user_id === null) {
                                        return '<span class="label label-primary">Всем пользователям</span>';
                                    }
                                    return Html::a(
                                        'ID: ' . $model->user_id,
                                        ['/user/view', 'id' => $model->user_id],
                                        ['title' => 'Просмотр пользователя']
                                    );
                                },
                                'filter' => [
                                    null => 'Всем пользователям',
                                    'not_null' => 'Конкретному пользователю',
                                ],
                            ],
                            
                            [
                                'attribute' => 'is_read',
                                'format' => 'html',
                                'value' => function ($model) {
                                    return $model->is_read 
                                        ? '<span class="label label-success">Прочитано</span>'
                                        : '<span class="label label-warning">Не прочитано</span>';
                                },
                                'filter' => [
                                    0 => 'Не прочитано',
                                    1 => 'Прочитано',
                                ],
                            ],
                            
                            [
                                'attribute' => 'priority',
                                'format' => 'html',
                                'value' => function ($model) {
                                    $priorities = [
                                        1 => '<span class="label label-danger">Критический</span>',
                                        2 => '<span class="label label-warning">Высокий</span>',
                                        3 => '<span class="label label-info">Обычный</span>',
                                        4 => '<span class="label label-default">Низкий</span>',
                                    ];
                                    return $priorities[$model->priority] ?? '<span class="label label-default">' . $model->priority . '</span>';
                                },
                                'filter' => Notification::getPriorityLabels(),
                            ],
                            
                            [
                                'attribute' => 'created_at',
                                'format' => 'datetime',
                                'filter' => false,
                            ],
                            
                            [
                                'attribute' => 'expires_at',
                                'format' => 'datetime',
                                'value' => function ($model) {
                                    if ($model->expires_at === null) {
                                        return '<span class="text-muted">Без ограничений</span>';
                                    }
                                    $expiresAt = date('Y-m-d H:i:s', $model->expires_at);
                                    $now = time();
                                    if ($model->expires_at < $now) {
                                        return '<span class="text-danger">Истекло: ' . $expiresAt . '</span>';
                                    }
                                    return $expiresAt;
                                },
                                'filter' => false,
                            ],
                            
                            [
                                'class' => 'yii\grid\ActionColumn',
                                'template' => '{view} {update} {delete} {mark-read}',
                                'buttons' => [
                                    'view' => function ($url, $model, $key) {
                                        return Html::a(
                                            '<i class="fa fa-eye"></i>',
                                            ['/notification/view', 'id' => $model->id],
                                            [
                                                'title' => 'Просмотр',
                                                'class' => 'btn btn-info btn-xs',
                                                'data-pjax' => '0',
                                            ]
                                        );
                                    },
                                    'update' => function ($url, $model, $key) {
                                        return Html::a(
                                            '<i class="fa fa-edit"></i>',
                                            ['/notification/update', 'id' => $model->id],
                                            [
                                                'title' => 'Редактировать',
                                                'class' => 'btn btn-primary btn-xs',
                                                'data-pjax' => '0',
                                            ]
                                        );
                                    },
                                    'delete' => function ($url, $model, $key) {
                                        return Html::a(
                                            '<i class="fa fa-trash"></i>',
                                            ['/notification/delete', 'id' => $model->id],
                                            [
                                                'title' => 'Удалить',
                                                'class' => 'btn btn-danger btn-xs',
                                                'data' => [
                                                    'confirm' => 'Вы уверены, что хотите удалить это уведомление?',
                                                    'method' => 'post',
                                                ],
                                            ]
                                        );
                                    },
                                    'mark-read' => function ($url, $model, $key) {
                                        if ($model->is_read) {
                                            return '';
                                        }
                                        return Html::a(
                                            '<i class="fa fa-check"></i>',
                                            ['/notification/mark-read', 'id' => $model->id],
                                            [
                                                'title' => 'Отметить как прочитанное',
                                                'class' => 'btn btn-success btn-xs',
                                                'data' => [
                                                    'method' => 'post',
                                                ],
                                            ]
                                        );
                                    },
                                ],
                            ],
                        ],
                    ]); ?>
                    
                    <?php Pjax::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.label-purple { background-color: #9c27b0; }
.label-pink { background-color: #e91e63; }
.label-orange { background-color: #ff9800; }
.label-yellow { background-color: #ffeb3b; color: #333; }
</style>

<?php

use backend\models\Notification;
use yii\helpers\Html;
use yii\helpers\Url;
use common\models\user\User;

/** @var yii\web\View $this */
/** @var Notification $model */

$this->title = 'Уведомление: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Уведомления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="notifications-view">
    <div class="row">
        <div class="col-md-8">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-bell"></i> <?= Html::encode($model->title) ?>
                    </h3>
                    <div class="box-tools pull-right">
                        <?= Html::a(
                            '<i class="fa fa-edit"></i> Редактировать',
                            ['update', 'id' => $model->id],
                            ['class' => 'btn btn-primary btn-sm']
                        ) ?>
                        <?= Html::a(
                            '<i class="fa fa-trash"></i> Удалить',
                            ['delete', 'id' => $model->id],
                            [
                                'class' => 'btn btn-danger btn-sm',
                                'data' => [
                                    'confirm' => 'Вы уверены, что хотите удалить это уведомление?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <?php
                            $alertClass = 'alert-info';
                            switch($model->type) {
                                case 'success':
                                    $alertClass = 'alert-success';
                                    break;
                                case 'warning':
                                    $alertClass = 'alert-warning';
                                    break;
                                case 'error':
                                    $alertClass = 'alert-danger';
                                    break;
                                case 'support':
                                    $alertClass = 'alert-primary';
                                    break;
                                case 'announcement':
                                    $alertClass = 'alert-info';
                                    break;
                            }
                            
                            $priorityLabels = [
                                1 => 'Критический',
                                2 => 'Высокий', 
                                3 => 'Обычный',
                                4 => 'Низкий',
                            ];
                            $priorityText = $priorityLabels[$model->priority] ?? 'Неизвестно';
                            ?>
                            
                            <div class="alert <?= $alertClass ?>">
                                <div class="row">
                                    <div class="col-md-10">
                                        <h4><?= Html::encode($model->title) ?></h4>
                                        <div><?= $model->message ?></div>
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <span class="badge badge-secondary"><?= $priorityText ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-info-circle"></i> Информация
                    </h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <tr>
                            <td><strong>ID:</strong></td>
                            <td><?= $model->id ?></td>
                        </tr>
                        <tr>
                            <td><strong>Тип:</strong></td>
                            <td>
                                <?php
                                $typeLabels = [
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
                                echo $typeLabels[$model->type] ?? '<span class="label label-default">' . $model->type . '</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Приоритет:</strong></td>
                            <td>
                                <?php
                                $priorityLabels = [
                                    1 => '<span class="label label-danger">Критический</span>',
                                    2 => '<span class="label label-warning">Высокий</span>',
                                    3 => '<span class="label label-info">Обычный</span>',
                                    4 => '<span class="label label-default">Низкий</span>',
                                ];
                                echo $priorityLabels[$model->priority] ?? '<span class="label label-default">' . $model->priority . '</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Получатель:</strong></td>
                            <td>
                                <?php if ($model->user_id === null): ?>
                                    <span class="label label-primary">Всем пользователям</span>
                                <?php else: ?>
                                    <?= Html::a(
                                        'ID: ' . $model->user_id,
                                        ['/user/view', 'id' => $model->user_id],
                                        ['title' => 'Просмотр пользователя']
                                    ) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Статус:</strong></td>
                            <td>
                                <?php if ($model->is_read): ?>
                                    <span class="label label-success">Прочитано</span>
                                <?php else: ?>
                                    <span class="label label-warning">Не прочитано</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Создано:</strong></td>
                            <td><?= Yii::$app->formatter->asDatetime($model->created_at) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Обновлено:</strong></td>
                            <td><?= Yii::$app->formatter->asDatetime($model->updated_at) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Истекает:</strong></td>
                            <td>
                                <?php if ($model->expires_at === null): ?>
                                    <span class="text-muted">Без ограничений</span>
                                <?php else: ?>
                                    <?php
                                    $expiresAt = Yii::$app->formatter->asDatetime($model->expires_at);
                                    $now = time();
                                    if ($model->expires_at < $now) {
                                        echo '<span class="text-danger">Истекло: ' . $expiresAt . '</span>';
                                    } else {
                                        echo $expiresAt;
                                    }
                                    ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="btn-group-vertical btn-block">
                        <?= Html::a(
                            '<i class="fa fa-edit"></i> Редактировать',
                            ['update', 'id' => $model->id],
                            ['class' => 'btn btn-primary']
                        ) ?>
                        <?= Html::a(
                            '<i class="fa fa-copy"></i> Дублировать',
                            ['create', 'duplicate' => $model->id],
                            ['class' => 'btn btn-default']
                        ) ?>
                        <?php if (!$model->is_read): ?>
                            <?= Html::a(
                                '<i class="fa fa-check"></i> Отметить как прочитанное',
                                ['mark-read', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-success',
                                    'data' => [
                                        'method' => 'post',
                                    ],
                                ]
                            ) ?>
                        <?php endif; ?>
                        <?= Html::a(
                            '<i class="fa fa-trash"></i> Удалить',
                            ['delete', 'id' => $model->id],
                            [
                                'class' => 'btn btn-danger',
                                'data' => [
                                    'confirm' => 'Вы уверены, что хотите удалить это уведомление?',
                                    'method' => 'post',
                                ],
                            ]
                        ) ?>
                    </div>
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

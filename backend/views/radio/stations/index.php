<?php

use yii\helpers\Html;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $stations \common\models\radio\RadioStation[] */

$this->title = Yii::t('common', 'Управление радиостанциями');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Радио'), 'url' => ['radio/index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="radio-stations-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(
            '<i class="fa fa-plus"></i> ' . Yii::t('common', 'Добавить радиостанцию'),
            ['radio/station'],
            ['class' => 'btn btn-success']
        ) ?>
        <?= Html::a(
            Yii::t('common', 'Модерация треков'),
            ['radio/index'],
            ['class' => 'btn btn-default']
        ) ?>
    </p>

    <?php if (empty($stations)): ?>
        <div class="alert alert-info">
            <?= Yii::t('common', 'Радиостанции не найдены.') ?>
        </div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= Yii::t('common', 'Название') ?></th>
                    <th><?= Yii::t('common', 'Порт') ?></th>
                    <th><?= Yii::t('common', 'URL потока') ?></th>
                    <th><?= Yii::t('common', 'Статус') ?></th>
                    <th><?= Yii::t('common', 'Запущена') ?></th>
                    <th><?= Yii::t('common', 'Треков') ?></th>
                    <th><?= Yii::t('common', 'Действия') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stations as $station): ?>
                    <tr>
                        <td><?= $station->id ?></td>
                        <td><strong><?= Html::encode($station->name) ?></strong></td>
                        <td><?= $station->port ?></td>
                        <td>
                            <?php if ($station->stream_url): ?>
                                <code><?= Html::encode($station->stream_url) ?></code>
                            <?php else: ?>
                                <span class="text-muted">localhost</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($station->status == 1): ?>
                                <span class="label label-success"><?= Yii::t('common', 'Активна') ?></span>
                            <?php else: ?>
                                <span class="label label-default"><?= Yii::t('common', 'Неактивна') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($station->is_running): ?>
                                <span class="label label-success"><?= Yii::t('common', 'Да') ?></span>
                            <?php else: ?>
                                <span class="label label-default"><?= Yii::t('common', 'Нет') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $tracksCount = $station->getRadioTracks()->count(); ?>
                            <?= $tracksCount ?>
                        </td>
                        <td>
                            <?= Html::a(
                                '<i class="fa fa-eye"></i>',
                                ['radio/station', 'id' => $station->id],
                                ['title' => Yii::t('common', 'Просмотр'), 'class' => 'btn btn-sm btn-info']
                            ) ?>
                            <?= Html::a(
                                '<i class="fa fa-pencil"></i>',
                                ['radio/station', 'id' => $station->id],
                                ['title' => Yii::t('common', 'Редактировать'), 'class' => 'btn btn-sm btn-primary']
                            ) ?>
                            <?= Html::a(
                                '<i class="fa fa-trash"></i>',
                                ['radio/delete-station', 'id' => $station->id],
                                [
                                    'title' => Yii::t('common', 'Удалить'),
                                    'class' => 'btn btn-sm btn-danger',
                                    'data-confirm' => Yii::t('common', 'Вы уверены что хотите удалить эту радиостанцию?'),
                                    'data-method' => 'post'
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>


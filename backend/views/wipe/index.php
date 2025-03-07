<?php

use common\models\box\Box;
use common\models\servers\Servers;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Box */

$this->title = Yii::t('common', 'Вайп');
?>
<?=\frontend\widgets\Alert::widget()?>

<div style="padding: 20px">
    <div style="margin-top: 10px;">
        <h3>Заблокировать предметы</h3>
        <?php
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        ?>
        <?php foreach ($servers as $server): ?>
            <?php $disabled = Yii::$app->cache->get("WIPE_actionBlock_{$server->id}") ? ' btn-default disabled' : ' btn-success' ?>
            <?= Html::a($server->name,
                    '/wipe/block?id=' . $server->id,
                    ['class' => 'btn' . $disabled]); ?>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 10px;">
        <h3>Начислить награды за топы</h3>
        <?php
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        ?>
        <?php foreach ($servers as $server): ?>
            <?php $disabled = Yii::$app->cache->get("WIPE_actionTop_{$server->tag}") ? ' btn-default disabled' : ' btn-success' ?>
        <?= Html::a($server->name,
                    '/wipe/top?server=' . $server->tag,
                    ['class' => 'btn' . $disabled]); ?>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 10px;">
        <h3>Зафиксировать карту</h3>
        <?php foreach ($servers as $server): ?>
        <?php $disabled = Yii::$app->cache->get("WIPE_actionSelectMap_{$server->id}") ? ' btn-default disabled' : ' btn-success' ?>
        <?= Html::a($server->name,
                    '/wipe/select-map?id=' . $server->id,
                    ['class' => 'btn' . $disabled, 'disabled' => true]); ?>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 10px;">
        <h3>Генерация новых карт</h3>
        <?php foreach ($servers as $server): ?>
        <?php $disabled = Yii::$app->cache->get("WIPE_actionGenerateMap_{$server->id}") ? ' btn-default disabled' : ' btn-success' ?>
        <?= Html::a($server->name,
                    '/wipe/generate-map?id=' . $server->id,
                    ['class' => 'btn' . $disabled, 'disabled' => true]); ?>
        <?php endforeach; ?>
    </div>
    <hr>
    <div style="margin-top: 10px;">
        <h3>Обнулить промокод</h3>
        <?= Html::a(Yii::t('common', 'WIPE'),
                    '/wipe/promocode',
                    ['class' => 'btn btn-success']); ?>
    </div>
    <hr>
    <div style="margin-top: 10px;">
        <h3>Задания</h3>
        <?= Html::a(Yii::t('common', 'Обнулить задания'),
                    '/wipe/task-clear',
                    ['class' => 'btn btn-success']); ?>
    </div>
</div>
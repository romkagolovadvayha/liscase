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
    <div>
        <h3>Вайп блок</h3>
        <?= Html::a(Yii::t('common', 'Заблокировать предметы в магазине'),
                    '/wipe/block',
                    ['class' => 'btn btn-success']); ?>
    </div>
    <hr>
    <div style="margin-top: 10px;">
        <h3>Начислить награды за топы</h3>
        <?php
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->all();
        ?>
        <?php foreach ($servers as $server): ?>
        <?= Html::a($server->name,
                    '/wipe/top?server=' . $server->tag,
                    ['class' => 'btn btn-success']); ?>
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
<?php

use common\models\box\Box;
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
        <?= Html::a(Yii::t('common', 'Заблокировать предметы в магазине'),
                    '/wipe/block',
                    ['class' => 'btn btn-success']); ?>
    </div>
    <div style="margin-top: 10px;">
        <?= Html::a(Yii::t('common', 'Начислить награды за топы'),
                    '/wipe/top',
                    ['class' => 'btn btn-success']); ?>
    </div>
    <div style="margin-top: 10px;">
        <?= Html::a(Yii::t('common', 'Обнулить промокод WIPE'),
                    '/wipe/promocode',
                    ['class' => 'btn btn-success']); ?>
    </div>
    <div style="margin-top: 10px;">
        <?= Html::a(Yii::t('common', 'Обнулить задания'),
                    '/wipe/task-clear',
                    ['class' => 'btn btn-success']); ?>
    </div>
</div>
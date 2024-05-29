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
<?= Html::a(Yii::t('common', 'Заблокировать предметы в магазине'),
    '/wipe/block',
    ['class' => 'btn btn-success']); ?>
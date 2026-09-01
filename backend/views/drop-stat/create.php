<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\box\DropStat $model */

$this->title = 'Добавить характеристику предмета';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="drop-stat-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

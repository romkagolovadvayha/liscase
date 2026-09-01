<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\box\DropStat $model */

$this->title = 'Изменить характеристику предмета';
?>
<div class="drop-stat-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

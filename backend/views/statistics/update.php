<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\statistics\Statistics $model */

$this->title = 'Update Statistics: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Statistics', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="statistics-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

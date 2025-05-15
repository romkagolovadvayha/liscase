<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\statistics\Statistics $model */

$this->title = 'Create Statistics';
$this->params['breadcrumbs'][] = ['label' => 'Statistics', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="statistics-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

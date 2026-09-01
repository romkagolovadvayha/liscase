<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\statistics\Statistics $model */

$this->title = 'Изменить статистику №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Статистика', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменение';
?>
<div class="statistics-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

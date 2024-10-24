<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */

$this->title = 'Update Building: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Buildings', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="building-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */

$this->title = 'Изменить: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Сервера', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменить';
?>
<div class="servers-update">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

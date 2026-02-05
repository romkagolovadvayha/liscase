<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRadioStation $model */

$this->title = 'Редактировать радиостанцию #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Радиостанции серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Радиостанция #' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактировать';
?>
<div class="servers-radio-station-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>


<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRadioStation $model */

$this->title = 'Создать радиостанцию';
$this->params['breadcrumbs'][] = ['label' => 'Радиостанции серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-radio-station-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>


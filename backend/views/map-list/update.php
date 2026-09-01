<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\map\MapList $model */

$this->title = 'Изменить карту №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Карты', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменение';
?>
<div class="map-list-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>


<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\map\Map $model */

$this->title = 'Добавить карту';
$this->params['breadcrumbs'][] = ['label' => 'Карты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="map-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

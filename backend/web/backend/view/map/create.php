<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\map\Map $model */

$this->title = 'Create Map';
$this->params['breadcrumbs'][] = ['label' => 'Maps', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="map-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

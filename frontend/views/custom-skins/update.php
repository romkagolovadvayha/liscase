<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */
/** @var \common\models\servers\Servers $server */

$this->title = 'Update Building: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Buildings', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="building-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'server' => $server,
    ]) ?>

</div>

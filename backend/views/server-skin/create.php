<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\serverskin\ServerSkin $model */

$this->title = 'Create Skin';
$this->params['breadcrumbs'][] = ['label' => 'Buildings', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="building-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

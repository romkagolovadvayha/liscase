<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\box\DropDrop $model */

$this->title = 'Добавить предмет в набор';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="drop-drop-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

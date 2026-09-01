<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\box\DropDrop $model */

$this->title = 'Изменить предмет набора';
?>
<div class="drop-drop-update">
    <?= $this->renderAjax('_form', [
        'model' => $model,
    ]) ?>
</div>

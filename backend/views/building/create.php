<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */

$this->title = Yii::t('common', 'Добавить постройку');
?>
<div class="building-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

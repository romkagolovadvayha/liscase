<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\building\Building $model */

$this->title = Yii::t('common', 'Редактировать постройку') . ': ' . Html::encode($model->name);
?>
<div class="building-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

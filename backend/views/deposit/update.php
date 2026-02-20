<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */

$this->title = Yii::t('common', 'Редактировать депозит') . ' #' . $model->id;
?>
<div class="deposit-update-page">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

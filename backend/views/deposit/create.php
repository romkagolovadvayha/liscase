<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */

$this->title = Yii::t('common', 'Добавить депозит');
?>
<div class="deposit-create-page">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

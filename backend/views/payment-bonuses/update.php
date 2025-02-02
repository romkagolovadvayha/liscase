<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\PaymentBonuses $model */

$this->title = 'Update Payment Bonuses: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Payment Bonuses', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="payment-bonuses-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

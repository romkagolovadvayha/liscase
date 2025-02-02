<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\PaymentBonuses $model */

$this->title = 'Create Payment Bonuses';
$this->params['breadcrumbs'][] = ['label' => 'Payment Bonuses', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="payment-bonuses-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>

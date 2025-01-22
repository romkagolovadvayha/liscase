<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\DepositBonus $model */

$this->title = 'Update Deposit Bonus: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Deposit Bonuses', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="deposit-bonus-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

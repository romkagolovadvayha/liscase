<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\PaymentBonuses $model */

$this->title = 'Изменить бонус при пополнении №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Бонусы при пополнении', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменение';
?>
<div class="payment-bonuses-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

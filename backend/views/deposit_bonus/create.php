<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\invoice\DepositBonus $model */

$this->title = 'Добавить депозитный бонус';
$this->params['breadcrumbs'][] = ['label' => 'Депозитные бонусы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="deposit-bonus-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

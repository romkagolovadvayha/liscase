<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\invoice\DepositBonus $model */

$this->title = 'Депозитный бонус №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Депозитные бонусы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="deposit-bonus-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить этот депозитный бонус?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'bonus',
            'min_amount',
        ],
    ]) ?>

</div>

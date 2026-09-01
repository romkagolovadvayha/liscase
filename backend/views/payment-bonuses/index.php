<?php

use common\models\invoice\PaymentBonuses;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\components\AccessibleGridView as GridView;

/** @var yii\web\View $this */
/** @var backend\models\invoice\PaymentBonusesSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Бонусы при пополнении';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wrap800">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="/payment-bonuses" class="nav-link active">Бонусы при пополнении</a>
        </li>
        <li class="nav-item">
            <a href="/payment-bonuses/create"
               class="nav-link show-modal-link"
               data-target="modal-dialog"
               data-title="Новый бонус">
                Новый бонус
            </a>
        </li>
    </ul>

    <div class="tab-content">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'amount',
            'bonus',
            'created_at',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, PaymentBonuses $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>
    </div>
</div>

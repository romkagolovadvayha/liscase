<?php

use common\models\invoice\DepositBonus;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\components\AccessibleGridView as GridView;

/** @var yii\web\View $this */
/** @var backend\models\invoice\DepositBonusSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Депозитные бонусы';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="deposit-bonus-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Добавить бонус', ['create'], [
            'class' => 'ds-btn ds-btn--primary',
        ]) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'bonus',
            'min_amount',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, DepositBonus $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

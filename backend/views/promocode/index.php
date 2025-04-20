<?php

use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\models\promocode\Promocode;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Promocode */

$this->title = Yii::t('common', 'Промокоды');
?>
<div class="wrap800">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="/promocode" class="nav-link active">Все промокоды</a>
        </li>
        <li class="nav-item">
            <a href="/promocode/create"
               class="nav-link show-modal-link"
               data-toggl="modal"
               data-target="modal-dialog"
               data-title="Новый промокод">
                Новый промокод
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'columns'      => [
                'code',
                [
                    'attribute' => 'status',
                    'filterType'  => GridView::FILTER_SELECT2,
                    'filter'    => ArrayHelper::merge(['' => 'Все'], Promocode::getStatusList()),
                    'options'   => ['width' => '120'],
                    'value'     => function (Promocode $model) {
                        return ArrayHelper::getValue(Promocode::getStatusList(), $model->status);
                    },
                ],
                [
                    'attribute' => 'amount',
                    'options'   => ['width' => '50'],
                ],
                [
                    'attribute' => 'finished_at',
                    'options'   => ['width' => '180'],
                    'class' => \common\components\grid\DateColumn::class,
                    'format'    => 'raw',
                    'value'          => function (Promocode $model) {
                        return date('d.m.Y H:i:s', strtotime($model->finished_at));
                    },
                ],
                [
                    'class'    => 'yii\grid\ActionColumn',
                    'template' => '{update}',
                    'options'  => ['width' => '30'],
                ],
            ],
        ]);
        ?>
    </div>
</div>

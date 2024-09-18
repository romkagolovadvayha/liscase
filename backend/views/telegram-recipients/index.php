<?php

use backend\models\TelegramRecipients;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model TelegramRecipients */

$this->title = Yii::t('common', 'Список получателей сообщений телеграм бота');
?>

<?= Html::a(Html::icon('plus') . ' ' . Yii::t('common', 'Создать новый список'),
    '/telegram-recipients/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'attribute' => 'id',
            'headerOptions' => ['style' => 'width:5%'],
        ],
        [
            'attribute' => 'ref_id',
            'filter'    => false,
            'headerOptions' => ['style' => 'width:45%'],
        ],
        [
            'attribute' => 'name',
            'filter'    => true,
            'headerOptions' => ['style' => 'width:20%'],
           // 'format' => 'html'
        ],
        [
            'attribute' => 'quantity',
            'filter'    => true,
            'headerOptions' => ['style' => 'width:10%'],
        ],
        [
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update} {delete}',
            'options'  => [
                    'width' => '45'
                ],
            'buttons'  => [
                'update' => function ($url, $model) {
                    return \common\components\grid\ManageButton::update($url);
                },
                'delete' => function ($url, $model) {
                    return \common\components\grid\ManageButton::delete($url);
                },
            ],
        ],
    ],
]);
?>

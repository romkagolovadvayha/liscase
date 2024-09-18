<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use backend\models\TelegramConstructorMessage;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\TelegramConstructorMessageSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Сообщения для рассылок';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="telegram-constructor-message-index">
    <p>
        <?= Html::a('Добавить', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '50'],
            ],
            [
                'attribute' => 'image_link',
                'options'   => ['width' => '60'],
                'label' => '',
                'filter'    => false,
                'format' => 'raw',
                'value'     => function (TelegramConstructorMessage $model) {
                    return Html::img($model->getPubUrl(), ['width' => '50px']);
                },
            ],
            'title',
            [
                'class' => \common\components\grid\DateColumn::class,
            ],
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'options'  => [
                    'width' => '90'
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
    ]); ?>


</div>

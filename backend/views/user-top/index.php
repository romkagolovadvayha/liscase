<?php

use common\models\user\UserTop;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var common\models\user\UserTopSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'User Tops';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-top-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create User Top', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'user_id',
            'key',
            'value',
            'server_id',
            'wipe',
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{update} {reset}',
                'options'  => ['width' => '90'],
                'buttons'  => [
                    'update' => function ($url, $model) {
                        return \common\components\grid\ManageButton::update($url);
                    },
                    'reset' => function ($url, $model) {
                        $url = '/user-top/reset?id=' . $model->id;
                        $confirm = Yii::t('common', 'Вы уверены, что хотите обнулить эту статистику?');
                        return \yii\bootstrap5\Html::a(Yii::t('common', 'Обнулить'), $url, [
                            'title'        => Yii::t('common', 'Обнулить'),
                            'data-confirm' => $confirm,
                            'class'        => 'btn btn-sm btn-default',
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>


</div>

<?php

use common\models\achievements\AchievementsDaily;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use backend\models\achievements\AchievementsDailySearch;

/** @var yii\web\View $this */
/** @var AchievementsDailySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Ежедневная награда';
?>
<?=\frontend\widgets\Alert::widget()?>
<div class="wrap800">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="/achievements-daily" class="nav-link active">Ежедневные награды</a>
        </li>
    </ul>

    <div class="tab-content">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'columns' => [
                [
                    'attribute' => 'daily',
                    'options'   => ['width' => '80'],
                    'format'    => 'raw',
                    'value'          => function (AchievementsDaily $model) {
                        return $model->daily . " день";
                    },
                ],
                [
                    'attribute' => 'image',
                    'label' => '',
                    'options'   => ['width' => '40'],
                    'format'    => 'raw',
                    'value'          => function (AchievementsDaily $model) {
                        return Html::img($model->drop->image(), ['style' => 'width: 32px']);
                    },
                ],
                [
                    'attribute' => 'drop_id',
                    'format'    => 'raw',
                    'value'          => function (AchievementsDaily $model) {
                        return $model->drop->name;
                    },
                ],
                [
                    'attribute' => 'amount',
                    'options'   => ['width' => '180'],
                    'format'    => 'raw',
                    'value'          => function (AchievementsDaily $model) {
                        return 'x' . $model->amount;
                    },
                ],
                [
                    'class'    => 'yii\grid\ActionColumn',
                    'template' => '{update}',
                    'options'  => ['width' => '30'],
                ],
            ],
        ]); ?>
    </div>
</div>

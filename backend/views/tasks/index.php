<?php

use common\models\tasks\Tasks;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Tasks';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tasks-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Tasks', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'image',
            'name',
            'short_name',
            'tasks_publish_place_id',
            //'tasks_projects_id',
            //'date_start',
            //'date_end',
            //'description',
            //'amount',
            //'amount_icon',
            //'additional_text',
            //'url_text:url',
            //'url_link:url',
            //'button_text',
            //'button_url:url',
            //'reward_amount_signature',
            //'additional_explanation',
            //'additional_url_text:url',
            //'additional_url_link:url',
            //'is_email_field:email',
            //'is_check_method_auto',
            //'is_permanent',
            //'is_publish',
            //'order_index',
            //'system_check_code',
            //'created_at',
            //'promotion_id',
            //'is_archive',
            //'lk_lang',
            //'video_link',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Tasks $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\tasks\Tasks $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Tasks', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="tasks-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'image',
            'name',
            'short_name',
            'tasks_publish_place_id',
            'tasks_projects_id',
            'date_start',
            'date_end',
            'description',
            'amount',
            'amount_icon',
            'additional_text',
            'url_text:url',
            'url_link:url',
            'button_text',
            'button_url:url',
            'reward_amount_signature',
            'additional_explanation',
            'additional_url_text:url',
            'additional_url_link:url',
            'is_email_field:email',
            'is_check_method_auto',
            'is_permanent',
            'is_publish',
            'order_index',
            'system_check_code',
            'created_at',
            'promotion_id',
            'is_archive',
            'lk_lang',
            'video_link',
        ],
    ]) ?>

</div>

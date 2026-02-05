<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\servers\ServersRadioStation;

/** @var yii\web\View $this */
/** @var ServersRadioStation $model */

$this->title = 'Радиостанция #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Радиостанции серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-radio-station-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Вы уверены, что хотите удалить эту радиостанцию?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            [
                'attribute' => 'url',
                'format' => 'raw',
                'value' => Html::a(Html::encode($model->url), $model->url, ['target' => '_blank']),
            ],
            [
                'attribute' => 'logo',
                'format' => 'raw',
                'value' => $model->logo ? Html::img($model->getLogoUrl(), ['style' => 'max-width: 200px; max-height: 200px;']) : '<span class="text-muted">Нет логотипа</span>',
            ],
            'sort',
            [
                'attribute' => 'status',
                'format' => 'raw',
                'value' => $model->status == ServersRadioStation::STATUS_ACTIVE 
                    ? '<span class="badge badge-success">Активна</span>'
                    : '<span class="badge badge-secondary">Неактивна</span>',
            ],
            'created_at:datetime',
            'updated_at:datetime',
        ],
    ]) ?>

</div>


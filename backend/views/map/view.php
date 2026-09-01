<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\map\Map $model */

$this->title = 'Карта №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Карты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="map-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить эту карту?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'mapId',
            'link',
            'seed',
            'size',
            'version',
            'image_link',
            'image_link_icons',
            'created_at',
        ],
    ]) ?>

</div>

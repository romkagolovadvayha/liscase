<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\achievements\AchievementsDaily $model */

$this->title = 'Ежедневная награда №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Ежедневные награды', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="achievements-daily-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить эту ежедневную награду?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'daily',
            'drop_id',
            'amount',
        ],
    ]) ?>

</div>

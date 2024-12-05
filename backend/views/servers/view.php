<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Servers', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="servers-view">

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
            'name:ntext',
            'wipe',
            'wipe_type',
            'next_wipe',
            'global_wipe',
            'description:ntext',
            'rules:ntext',
            'ip:ntext',
            'port',
            'query',
            'rcon',
            'rcon_password',
            'map:ntext',
            'players',
            'joined',
            'queued',
            'team_limit',
            'max',
            'status',
            'db_host:ntext',
            'db_name:ntext',
            'db_user:ntext',
            'db_password:ntext',
            'tag:ntext',
            'stats_payment',
            'skindrops',
            'wargm_id',
            'commands:ntext',
            'discord_token',
        ],
    ]) ?>

</div>

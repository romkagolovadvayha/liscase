<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Серверы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="servers-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить этот сервер?',
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
            [
                'attribute' => 'rcon_password',
                'value' => static function ($model) {
                    return $model->rcon_password ? '••••••••' : '—';
                },
            ],
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
            [
                'attribute' => 'db_password',
                'value' => static function ($model) {
                    return $model->db_password ? '••••••••' : '—';
                },
            ],
            'tag:ntext',
            'stats_payment',
            'skindrops',
            'wargm_id',
            'commands:ntext',
            [
                'attribute' => 'discord_token',
                'value' => static function ($model) {
                    return $model->discord_token ? '••••••••' : '—';
                },
            ],
        ],
    ]) ?>

</div>

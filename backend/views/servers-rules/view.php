<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRules $model */

$this->title = 'Правило #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Правила серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-rules-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Вы уверены, что хотите удалить это правило?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'category_id',
                'value' => $model->category ? $model->category->name : '',
            ],
            [
                'attribute' => 'server_id',
                'label' => 'Серверы',
                'format' => 'raw',
                'value' => function($model) {
                    $servers = $model->servers;
                    if (empty($servers)) {
                        return '<span class="badge badge-info">Общее правило (для всех серверов)</span>';
                    }
                    $serverNames = [];
                    foreach ($servers as $server) {
                        $serverNames[] = '<span class="badge badge-secondary">' . Html::encode($server->name) . '</span>';
                    }
                    return implode(' ', $serverNames);
                },
            ],
            'title',
            [
                'attribute' => 'content',
                'format' => 'html',
            ],
            'punishment',
            'sort',
            'created_at:datetime',
            'updated_at:datetime',
        ],
    ]) ?>

</div>


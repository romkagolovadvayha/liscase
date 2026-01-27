<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersTags $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="servers-tags-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Вы уверены, что хотите удалить этот тег?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            'title',
            'link_name',
            'short_description',
            'description:ntext',
            [
                'attribute' => 'color',
                'format' => 'raw',
                'value' => '<span class="badge" style="background-color: ' . $model->color . '; padding: 10px 20px;">' . $model->color . '</span>',
            ],
            'sort',
            [
                'attribute' => 'status',
                'value' => $model->getStatusName(),
            ],
            'created_at:datetime',
            'updated_at:datetime',
        ],
    ]) ?>

    <h3>Серверы с этим тегом:</h3>
    <?php if (!empty($model->servers)): ?>
        <ul>
            <?php foreach ($model->servers as $server): ?>
                <li>
                    <?= Html::a(Html::encode($server->name), ['/servers/view', 'id' => $server->id]) ?>
                    (<?= $server->tag ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">Нет серверов с этим тегом</p>
    <?php endif; ?>

</div>


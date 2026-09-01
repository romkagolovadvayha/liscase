<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersTags $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$tagColor = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $model->color) ? $model->color : '#566272';
\yii\web\YiiAsset::register($this);
?>
<div class="servers-tags-view admin-form-page">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('<i class="fas fa-pen" aria-hidden="true"></i> Редактировать', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
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
                'value' => Html::tag('span', Html::encode($model->color), [
                    'class' => 'servers-tag-color-preview',
                    'style' => ['--servers-tag-color' => $tagColor],
                ]),
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
                    (<?= Html::encode($server->tag) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">Нет серверов с этим тегом</p>
    <?php endif; ?>

</div>


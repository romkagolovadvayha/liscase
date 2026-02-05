<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRulesCategory $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Категории правил', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-rules-category-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Вы уверены, что хотите удалить эту категорию?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            'icon',
            'sort',
            'created_at:datetime',
            'updated_at:datetime',
        ],
    ]) ?>

</div>


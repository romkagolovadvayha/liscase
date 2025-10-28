<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersTags $model */

$this->title = 'Редактировать тег: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги серверов', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="servers-tags-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>



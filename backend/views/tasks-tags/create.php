<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksTags $model */

$this->title = 'Create Tasks Tags';
$this->params['breadcrumbs'][] = ['label' => 'Tasks Tags', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tasks-tags-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

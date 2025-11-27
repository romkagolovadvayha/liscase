<?php

use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = Yii::t('common', 'Создать задание');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Задания v2'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="tasks-v2-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>












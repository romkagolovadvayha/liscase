<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \common\models\tasks_v2\TaskV2 $model */

$this->title = Yii::t('common', 'Редактировать задание');
$this->params['breadcrumbs'][] = ['label' => Yii::t('common', 'Задания v2'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = Yii::t('common', 'Редактировать');

?>
<div class="tasks-v2-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>












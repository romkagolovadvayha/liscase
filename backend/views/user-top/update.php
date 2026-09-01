<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\user\UserTop $model */

$this->title = 'Изменить запись топа №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Топ игроков', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменение';
?>
<div class="user-top-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\user\UserClan $model */

$this->title = 'Update User Clan: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'User Clans', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="user-clan-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\user\UserClan $model */

$this->title = 'Create User Clan';
$this->params['breadcrumbs'][] = ['label' => 'User Clans', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-clan-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

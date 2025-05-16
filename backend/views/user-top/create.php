<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\user\UserTop $model */

$this->title = 'Create User Top';
$this->params['breadcrumbs'][] = ['label' => 'User Tops', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-top-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

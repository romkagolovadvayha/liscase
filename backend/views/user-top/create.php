<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\user\UserTop $model */

$this->title = 'Добавить запись в топ';
$this->params['breadcrumbs'][] = ['label' => 'Топ игроков', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-top-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

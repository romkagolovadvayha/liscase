<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */

$this->title = 'Новый сервер';
$this->params['breadcrumbs'][] = ['label' => 'Сервера', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>

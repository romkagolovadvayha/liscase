<?php

/* @var $this yii\web\View */
/* @var $model backend\models\TelegramConstructorMessage */

$this->title = 'Редактирование шаблона' . ($model->title ? ': ' . $model->title : ' #' . $model->id);
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Рассылки', 'url' => ['/telegram-constructor/index']];
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="telegram-constructor-message-update">
    <?= $this->render('_form', ['model' => $model]) ?>
</div>

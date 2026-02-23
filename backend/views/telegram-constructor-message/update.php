<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model backend\models\TelegramConstructorMessage */

$this->title = 'Изменить сообщение' . ($model->title ? ': ' . $model->title : ' #' . $model->id);
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = ['label' => 'Сообщения для рассылок', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Изменить';
?>
<div class="telegram-constructor-message-update">
    <?= $this->render('_form', ['model' => $model]) ?>
</div>

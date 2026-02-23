<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model backend\models\TelegramConstructorMessage */

$this->title = 'Создать сообщение';
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = ['label' => 'Сообщения для рассылок', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="telegram-constructor-message-create">
    <?= $this->render('_form', ['model' => $model]) ?>
</div>

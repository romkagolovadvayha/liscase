<?php

/* @var $this yii\web\View */
/* @var $model backend\models\TelegramConstructorMessage */

$this->title = 'Новый шаблон рассылки';
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Рассылки', 'url' => ['/telegram-constructor/index']];
$this->params['breadcrumbs'][] = ['label' => 'Шаблоны', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="telegram-constructor-message-create">
    <?= $this->render('_form', ['model' => $model]) ?>
</div>

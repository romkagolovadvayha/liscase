<?php
/* @var $model */

$this->title = Yii::t('common', 'Обновить рассылку');
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = $this->title;

echo $this->render('_form', ['model' => $model]);
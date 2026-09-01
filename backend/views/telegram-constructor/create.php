<?php
/* @var $model */

$this->title = Yii::t('common', 'Новая рассылка');
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Рассылки', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = $this->title;

echo $this->render('_form', ['model' => $model]);

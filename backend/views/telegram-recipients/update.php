<?php
/* @var $model */

$this->title = Yii::t('common', 'Редактировать аудиторию');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

<?php
/* @var $model */

$this->title = Yii::t('common', 'Новая аудитория');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

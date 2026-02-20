<?php
/* @var $model */

$this->title = Yii::t('common', 'Изменить предмет');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);
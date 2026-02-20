<?php
/** @var common\models\servers\ServersTags $model */

$this->title = Yii::t('common', 'Создать тег');
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;

echo $this->render('_form', ['model' => $model]);

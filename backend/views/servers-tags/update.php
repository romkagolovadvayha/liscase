<?php
/** @var common\models\servers\ServersTags $model */

$this->title = Yii::t('common', 'Редактировать тег: {name}', ['name' => $model->name]);
$this->params['contentClass'] = 'content-no-padding';
$this->params['showFilters'] = false;

echo $this->render('_form', ['model' => $model]);

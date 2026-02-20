<?php
/** @var common\models\servers\Servers $model */

$this->title = Yii::t('common', 'Изменить: {name}', ['name' => $model->name]);
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

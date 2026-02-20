<?php
/** @var backend\forms\blog\BlogForm $model */

$this->title = Yii::t('common', 'Добавить пост');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

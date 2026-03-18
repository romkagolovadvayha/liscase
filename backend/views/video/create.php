<?php

/** @var yii\web\View $this */
/** @var common\models\video\UserVideo $model */

$this->title = Yii::t('common', 'Добавить видео');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

<?php
/* @var $model */

$this->title = Yii::t('common', 'Создать список получателей сообщений телеграм бота');

echo $this->render('_form', ['model' => $model]);
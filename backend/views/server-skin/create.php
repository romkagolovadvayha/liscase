<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\serverskin\ServerSkin $model */

$this->title = Yii::t('common', 'Добавить скин');
$this->params['contentClass'] = 'content-no-padding';

echo $this->render('_form', ['model' => $model]);

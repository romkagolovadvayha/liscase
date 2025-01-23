<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var string $category */

/** @var \common\models\site\SiteSetting[] $settings */
$settings = SiteSetting::find()
                       ->andWhere(['category' => $category])
                       ->indexBy('id')
                       ->all();

$this->title = 'Настройки сайта';
?>

<div class="setting">
    <?=$this->render('form', ['category' => 'site', 'title' => "Настройки сайта"])?>
    <?=$this->render('form', ['category' => 'social', 'title' => "Социальные сети"])?>
    <?=$this->render('form', ['category' => 'section', 'title' => "Разделы сайта"])?>
</div>
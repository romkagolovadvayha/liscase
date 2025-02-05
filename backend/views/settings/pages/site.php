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

$tabs = [
    [
        'category' => 'site',
        'title' => 'Настройки сайта',
    ],
    [
        'category' => 'social',
        'title' => 'Социальные сети',
    ],
    [
        'category' => 'section',
        'title' => 'Разделы сайта',
    ],
    [
        'category' => 'banSystem',
        'title' => 'Бан система',
    ],
    [
        'category' => 'metrics',
        'title' => 'Счетчики',
    ],
];
?>

<div class="wrap800">
    <?=$this->render('tabs', ['tabs' => $tabs])?>
</div>
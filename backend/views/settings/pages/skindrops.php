<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var string $category */

$this->title = 'Настройки дизайна';
$tabs = [
    [
        'category' => 'skindrops',
        'title' => 'Раздача скинов',
    ],
    [
        'category' => 'rusttm',
        'title' => 'Rust.Tm',
    ],
];
?>

<div class="wrap800">
    <?=$this->render('tabs', ['tabs' => $tabs])?>
</div>
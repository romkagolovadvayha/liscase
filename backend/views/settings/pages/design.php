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
        'category' => 'design',
        'title' => 'Дизайн',
        'setting_items_class' => 'setting_items_flex',
    ],
    [
        'category' => 'colors',
        'title' => 'Настройки темы',
        'setting_items_class' => 'setting_items_flex',
    ],
];
?>

<?=$this->render('tabs', ['tabs' => $tabs])?>
<?php

use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\widgets\Pjax;

/** @var string $category */

$this->title = 'Настройки';
?>

<div class="setting">
    <?=$this->render('form', ['category' => $category])?>
</div>
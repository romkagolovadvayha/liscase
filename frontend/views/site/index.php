<?php

/** @var yii\web\View $this */

use yii\base\BaseObject;
use common\models\box\Box;

$this->title = Yii::$app->name . ' - Лучшие CSGO кейсы';


?>

<h2>Бесплатные кейсы</h2>
<?php foreach (Box::getBoxesByType(Box::TYPE_FREE) as $box): ?>
    <div class="box">
        <img src="<?=$box->imageOrig->getImagePubUrl()?>">
    </div>
<?php endforeach; ?>

<h2>Лучшие кейсы</h2>
<?php foreach (Box::getBoxesByType(Box::TYPE_DEFAULT) as $box): ?>
    <div class="box">
        <img src="<?=$box->imageOrig->getImagePubUrl()?>">
    </div>
<?php endforeach; ?>
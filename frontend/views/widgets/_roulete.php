<?php

use yii\web\View;
use common\models\box\BoxDrop;

/** @var View $this */
/** @var BoxDrop $boxDropCarousel */
/** @var int $number */

?>
<div class="roulete_wrapper" data-success="<?=$number?>">
    <div class="roulete_slider_wrap">
        <div class="roulete_main_wrap">
            <div class="slider roulete">
                <?php foreach ($boxDropCarousel as $boxDrop): ?>
                    <div class="roulete_item<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                        <div class="roulete_item_image">
                            <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $boxDrop->drop->name)?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="roulete_blur_wrap">
            <div class="slider roulete_blur">
                <?php foreach ($boxDropCarousel as $boxDrop): ?>
                    <div class="roulete_blur_item<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                        <div class="roulete_blur_item_image">
                            <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $boxDrop->drop->name)?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
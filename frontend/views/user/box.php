<?php

use yii\web\View;
use common\models\user\UserBox;

/** @var View $this */
/** @var UserBox $userBox */

$this->title = Yii::t('common', $userBox->box->name . " кейс для CS GO");

\common\assets\SlickCarouselAsset::register($this);
\frontend\assets\UserBoxAsset::register($this);

$boxDropCarousel = $userBox->box->boxDropCarousel;
?>

<main id="main" role="main">
    <div class="container">
        <div class="roulete_wrapper">
            <div class="roulete_slider_wrap">
                <div class="roulete_main_wrap">
                    <div class="slider roulete">
                        <?php foreach ($boxDropCarousel as $boxDrop): ?>
                            <div class="roulete_item<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                                <div class="roulete_item_image">
                                    <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('common', $boxDrop->drop->name)?>">
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
                                    <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('common', $boxDrop->drop->name)?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="roulete_actions" id="roulete_start">
                <button type="button" class="btn">Крутить</button>
            </div>
        </div>
    </div>
</main>

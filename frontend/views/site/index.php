<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

use common\models\box\Box;

$this->title = Yii::$app->name . ' - Лучшие CSGO кейсы';

\frontend\assets\LastDropAsset::register($this);

?>
<?= $this->render('@frontend/views/layouts/_promocode_line', [
    'promocodeForm' => $promocodeForm
]); ?>
<div class="last_drops_wrapper">
    <?= $this->render('@frontend/views/widgets/_last_drops'); ?>
</div>

<main id="main" role="main">
    <div class="container">
        <div class="boxes_free_wrapper">
            <div class="header">
                <h2>Бесплатные кейсы</h2>
                <p>Получи дроп бесплатно!</p>
            </div>
            <div class="boxes_free">
                <?php foreach (Box::getBoxesByType(Box::TYPE_FREE) as $box): ?>
                    <a href="/box/view?id=<?=$box->id?>" class="boxes_free_item">
                        <div class="boxes_free_item_image">
                            <img src="<?= $box->imageOrig->getImagePubUrl() ?>" alt="<?= $box->name ?>" width="100px">
                        </div>
                        <div class="boxes_free_item_title"><?= $box->name ?></div>
                        <div class="boxes_free_item_price">
                            <span class="boxes_top_item_price_old"><s><?= $box->price  ?> ₽</s></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="boxes_top_wrapper">
            <div class="header">
                <h2>Лучшие кейсы</h2>
                <p>Кейсы по низким ценам!</p>
            </div>
            <div class="boxes_top">
                <?php foreach (Box::getBoxesByType(Box::TYPE_DEFAULT) as $box): ?>
                    <a href="/box/view?id=<?=$box->id?>" class="boxes_top_item">
                        <div class="boxes_top_item_image">
                            <img src="<?= $box->imageOrig->getImagePubUrl() ?>" alt="<?= $box->name ?>" width="100px">
                        </div>
                        <div class="boxes_top_item_title"><?= $box->name ?></div>
                        <div class="boxes_top_item_price">
                            <span class="boxes_top_item_price_current"><?= $box->price ?> ₽</span>
                            <span class="boxes_top_item_price_old"><s><?= ($box->price + 1) * 2  ?> ₽</s></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
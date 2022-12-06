<?php

use common\models\box\Drop;
use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;

/** @var Drop $model */
/** @var string $balance */

if (empty($model->imageOrig)) {
    return null;
}
$image = Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
?>

<div class="market_drop_item">
    <div class="market_drop_item_image"><?=$image?></div>
    <div class="market_drop_item_name"><?=$model->getShortName()?></div>
    <div class="market_drop_item_quality"><?=$model->quality?></div>
    <?php $form = ActiveForm::begin(); ?>
    <a class="market_drop_item_btn" href="/market/view?id=<?=$model->id?>" <?=$balance < $model->priceMarket ? 'disabled' : ''?>>
        Купить
        <span class="market_drop_item_price"><?=$model->getPriceFormat()?> <span class="currency"><?=$model->currency?></span></span>
    </a>
    <?php ActiveForm::end(); ?>
</div>

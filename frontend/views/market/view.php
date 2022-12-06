<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Drop $drop */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;

$this->title = Yii::t('common', $drop->name);

\common\assets\SlickCarouselAsset::register($this);
$this->registerJs(<<<JS
    $('.market_view_boxes').slick({
        centerMode: true,
        centerPadding: '60px',
        slidesToShow: 3,
        arrows: false,
        slidesToScroll: 1
        });
JS
    , \yii\web\View::POS_END);
?>

<main id="main" role="main">
    <div class="container">
        <div class="market_entity_wrapper">
            <div class="row">
                <div class="col-md-4">
                    <div class="market_entity">
                        <div class="market_entity_card">
                            <div class="market_entity_card_title"><?= $drop->name ?></div>
                            <div class="market_entity_card_image">
                                <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= $drop->name ?>" width="200px">
                            </div>
                            <?php if (Yii::$app->user->isGuest): ?>
                                <div class="market_entity_card_alert">
                                    <div class="market_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
                                    <div class="market_entity_card_alert_text"><?=Yii::t('common', 'Для открытия кейсов необходимо пройти авторизацию')?></div>
                                </div>
                                <div class="market_entity_card_actions">
                                    <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                                        <span><?=Yii::t('common', 'Войти через Steam')?></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="market_entity_card_actions">
                                    <a class="market_entity_card_actions_btn" href="#">
                                        <span class="market_entity_card_actions_btn_text"><?=Yii::t('common', 'Купить')?></span>
                                        <span class="market_entity_card_actions_btn_price">
                                            <span class="currency"><?=$drop->currency?></span>
                                            <span class="price"><?=$drop->getPriceFormat()?></span>
                                        </span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="market_view_content_wrapper">
                        <div class="market_view_content">
                            <p class="market_view_content_description">
                                <?=Yii::t('common', $drop->description)?>
                            </p>
                            <ul class="market_view_content_list">
                                <li class="category">
                                    <?=Yii::t('common', 'Категория')?>: <a href="/market/index?DropSearch%5Btype_id%5D=<?=$drop->type->id?>"><?=Yii::t('common', $drop->type->name)?></a>
                                </li>
                                <li class="quality">
                                    <?=Yii::t('common', 'Качество')?>: <a href="/market/index?DropSearch%5Bquality%5D=<?=urlencode($drop->quality)?>"><?=Yii::t('common', $drop->quality)?></a>
                                </li>
                                <li class="price">
                                    <?=Yii::t('common', 'Цена')?>: <?=$drop->getPriceFormat()?> <span class="currency"><?=$drop->currency?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="market_view_boxes_wrapper">
                        <h2><?=Yii::t('common', 'Может выпасть в кейсах')?></h2>
                        <div class="market_view_boxes">
                            <?php foreach ($drop->boxDrop as $boxDrop): ?>
                                <a href="/box/view?id=<?=$boxDrop->box->id?>" class="market_view_boxes_item">
                                    <div class="market_view_boxes_item_image">
                                        <img src="<?= $boxDrop->box->imageOrig->getImagePubUrl() ?>" alt="<?= $boxDrop->box->name ?>" width="100px">
                                    </div>
                                    <div class="market_view_boxes_item_title"><?= $boxDrop->box->name ?></div>
                                    <div class="market_view_boxes_item_price">
                                        <span class="market_view_boxes_item_price_current"><?=$boxDrop->box->getPriceFinal()?></span>
                                        <?php if ($boxDrop->box->getPriceFinal() < $boxDrop->box->price): ?>
                                            <span class="market_view_boxes_item_price_old"><s><?=$boxDrop->box->price?></s></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>


<?php if (!Yii::$app->user->isGuest):?>
    <div class="modal modal-alert fade" id="openBoxModal" tabindex="-1" aria-labelledby="openBoxModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"><?=Yii::t('common', 'Внимание!')?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?=Yii::t('common', 'С вашего баланса будет списано')?> <?=$drop->getPriceCeil()?> <span class="currency"><?=$drop->currency?></span>.</p>
                    <p><?=Yii::t('common', 'Вы уверены?')?></p>
                    <?php $form = ActiveForm::begin([
                        'id' => 'buy',
                        'action' => 'buy?id=' . $drop->id,
                    ]); ?>
                    <input type="hidden" name="buy" value="1"/>
                    <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Отмена')?></button>
                    <button type="submit" class="btn" data-bs-dismiss="modal"><?=Yii::t('common', 'Продолжить')?></button>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
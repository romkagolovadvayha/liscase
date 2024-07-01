<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Box $box */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;

[$boxDropCarousel, $number] = $box->_getDrop();

if ($box->type === \common\models\box\Box::TYPE_FREE && !empty($getNextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate())) {
    return;
}

$this->registerJs(<<<JS
    var boxPrice = {$box->getPriceFinal()};
JS
    , \yii\web\View::POS_BEGIN);
?>

    <div class="box_entity_wrapper">
        <?php if (Yii::$app->user->isGuest): ?>
            <div class="box_entity">
                <div class="box_entity_card">
                    <div class="box_entity_card_image">
                        <img src="<?= $box->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database',  $box->name)?>" width="200px">
                    </div>
                    <div class="box_entity_card_alert">
                        <div class="box_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
                        <div class="box_entity_card_alert_text"><?=Yii::t('common', 'Для открытия кейсов необходимо пройти авторизацию')?></div>
                    </div>
                    <div class="box_entity_card_actions">
                        <a href="/auth/oauth?authclient=steam" class="box_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                            <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!Yii::$app->user->isGuest): ?>
            <div class="open_case_content_wrapper">
                <div class="open_case_content">
                        <div class="roulete_open_content_wrapp">
                            <div class="roulete_open_content">
                                <?=$this->render('../widgets/_roulete', [
                                    'boxDropCarousel' => $boxDropCarousel,
                                    'number' => $number,
                                ])?>
                            </div>
                            <div class="win_drop" id="win_drop">

                            </div>
                        </div>
                </div>
            </div>

            <div class="box_entity_card_actions">
                <?php $form = ActiveForm::begin([
                                                    'id' => 'roulete-container',
                                                    'action' => '/box/buy-container?id=' . $box->id,
                                                ]); ?>
                <input type="hidden" name="buy" value="1"/>
                <button class="box_entity_card_actions_btn<?=($box->getPriceFinal() <= 0) ? ' box_entity_card_actions_btn_free' : '' ?>" type="submit">
                    <?=Yii::t('common', 'Открыть контейнер')?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php if ($box->getPriceFinal() <= 0): ?>
                                            <?=Yii::t('common', 'Бесплатно')?>
                                        <?php else: ?>
                                            <?=$box->getPriceFinal()?> <span class="currency"><?=$box->currency?></span>
                                        <?php endif; ?>
                                    </span>
                </button>
                <?php ActiveForm::end(); ?>
                <div class="box_entity_card_actions_inventory_action" style="display: none">
                    <a class="btn box_entity_card_actions_inventory_btn" href="/user/inventory">
                        <?=Yii::t('common', 'Открыть инвентарь')?>
                    </a>
                    <p><?=Yii::t('common',  'Следующий бесплатный контейнер будет доступен через 18 часов')?></p>
                </div>
            </div>
        <?php endif; ?>
        <div class="box_content_wrapper">
            <h2><?=Yii::t('common', 'Может выпасть')?></h2>
            <div class="box_content">
                <?php foreach ($box->boxDrop as $boxDrop): ?>
                    <a href="/market/view?id=<?= $boxDrop->drop->id ?>" class="box_content_card<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                        <div class="box_content_card_image">
                            <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $boxDrop->drop->name)?>">
                        </div>
                        <div class="box_content_card_info">
                            <div class="box_content_card_info_title"><?=$boxDrop->drop->getShortName()?></div>
                            <div class="box_content_card_info_type">x<?=$boxDrop->drop->min_box?> - x<?=$boxDrop->drop->max_box?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
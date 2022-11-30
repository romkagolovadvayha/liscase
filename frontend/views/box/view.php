<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Box $box */

use yii\bootstrap5\ActiveForm;

$this->title = Yii::t('common', $box->name . " кейс для CS GO");

\common\assets\SlickCarouselAsset::register($this);
\frontend\assets\UserBoxAsset::register($this);
?>

<main id="main" role="main">
    <div class="container">
        <div class="box_entity_wrapper">
            <div class="box_entity">
                <div class="box_entity_card">
                    <div class="box_entity_card_title"><?= $box->name ?></div>
                    <div class="box_entity_card_image">
                        <img src="<?= $box->imageOrig->getImagePubUrl() ?>" alt="<?= $box->name ?>" width="200px">
                    </div>
                    <?php if (Yii::$app->user->isGuest):?>
                        <div class="box_entity_card_alert">
                            <div class="box_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
                            <div class="box_entity_card_alert_text"><?=Yii::t('common', 'Для открытия кейсов необходимо пройти авторизацию')?></div>
                        </div>
                        <div class="box_entity_card_actions">
                            <a href="/auth/oauth?authclient=steam" class="box_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                                <span><?=Yii::t('common', 'Войти через Steam')?></span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="box_entity_card_actions">
                            <a class="box_entity_card_actions_btn" data-bs-toggle="modal" href="#openBoxModal" role="button">
                                <?=Yii::t('common', 'Открыть контейнер')?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php if ($box->type === \common\models\box\Box::TYPE_FREE): ?>
                                        <?=Yii::t('common', 'Бесплатно')?>
                                    <?php else: ?>
                                        <?=$box->price?> ₽
                                    <?php endif; ?>
                                </span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="box_content_wrapper">
                <h2><?=Yii::t('common', 'СОДЕРЖИМОЕ КЕЙСА')?></h2>
                <div class="box_content">
                    <?php foreach ($box->boxDrop as $boxDrop): ?>
                        <div class="box_content_card<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                            <div class="box_content_card_info">
                                <div class="box_content_card_info_type"><?=Yii::t('common', $boxDrop->drop->type->name)?></div>
                                <div class="box_content_card_info_title"><?=Yii::t('common', $boxDrop->drop->getShortName())?></div>
                            </div>
                            <div class="box_content_card_image">
                                <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('common', $boxDrop->drop->name)?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>


<div id="roulete_open" class="roulete_open">
    <div class="container">
<!--        <div id="prestarimg" class="prestar">-->
<!--            <div id="pstarB" class="pstar"></div>-->
<!--            <div id="pstarL" class="pstar"></div>-->
<!--        </div>-->
        <div class="roulete_open_content">
            <div class="loader">
                <img src="/images/icons/loader.svg"/>
            </div>
        </div>
    </div>
</div>


<?php if (!Yii::$app->user->isGuest):?>
    <?php $balance = Yii::$app->user->identity->getPersonalBalance(); ?>
    <?php if ($balance->balance >= $box->price):?>
        <div class="modal modal-alert fade" id="openBoxModal" tabindex="-1" aria-labelledby="openBoxModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel"><?=Yii::t('common', 'Внимание!')?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><?=Yii::t('common', 'С вашего баланса будет списано')?> <?=$box->price?> <?=Yii::t('common', 'рублей')?>.</p>
                        <p><?=Yii::t('common', 'Вы уверены?')?></p>
                        <?php $form = ActiveForm::begin([
                            'id' => 'buy-container',
                            'action' => 'buy-container?id=' . $box->id,
                            ]); ?>
                            <input type="hidden" name="buy" value="1"/>
                            <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Отмена')?></button>
                            <button type="submit" class="btn" data-bs-dismiss="modal"><?=Yii::t('common', 'Продолжить')?></button>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($box->type === \common\models\box\Box::TYPE_FREE): ?>
        <div class="modal modal-alert fade" id="openBoxModal" tabindex="-1" aria-labelledby="openBoxModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel"><?=Yii::t('common', 'Внимание!')?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><?=Yii::t('common', 'Вы уверены?')?></p>
                        <?php $form = ActiveForm::begin([
                            'id' => 'buy-free-container',
                            'action' => 'buy-container?id=' . $box->id,
                        ]); ?>
                        <input type="hidden" name="buy" value="1"/>
                        <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Отмена')?></button>
                        <button type="submit" class="btn" data-bs-dismiss="modal"><?=Yii::t('common', 'Продолжить')?></button>
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="modal modal-alert fade" id="openBoxModal" tabindex="-1" aria-labelledby="openBoxModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel"><?=Yii::t('common', 'Внимание!')?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><?=Yii::t('common', 'На вашем балансе недостаточно средств')?>.</p>
                        <a href="user/payment" class="btn"><?=Yii::t('common', 'Пополнить')?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
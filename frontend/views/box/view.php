<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Box $box */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;

$this->title = Yii::t('database',  $box->name) . ' ' . Yii::t('common',  "кейс для CS GO");

\common\assets\SlickCarouselAsset::register($this);
\frontend\assets\UserBoxAsset::register($this);

[$boxDropCarousel, $number] = $box->_getDrop();
$this->registerJs(<<<JS
    var boxPrice = {$box->getPriceFinal()};
JS
    , \yii\web\View::POS_BEGIN);
?>

<main id="main" role="main">
    <div class="container">
        <div class="box_entity_wrapper">
            <div class="header_actions">
                <a href="/"><?=Yii::t('common',  "Вернуться назад")?></a>
            </div>
            <div class="box_entity">
                <div class="box_entity_card">
                    <div class="box_entity_card_title"><?=Yii::t('database',  $box->name)?></div>
                    <div class="box_entity_card_image">
                        <img src="<?= $box->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database',  $box->name)?>" width="200px">
                    </div>
    <?php if (Yii::$app->user->isGuest): ?>
        <div class="box_entity_card_alert">
            <div class="box_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
            <div class="box_entity_card_alert_text"><?=Yii::t('common', 'Для открытия кейсов необходимо пройти авторизацию')?></div>
        </div>
        <div class="box_entity_card_actions">
            <a href="/auth/oauth?authclient=steam" class="box_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </div>
    <?php endif; ?>
                </div>
            </div>
            <?php if (!Yii::$app->user->isGuest): ?>
                <div class="open_case_content_wrapper">
                    <div class="open_case_content">
                        <?php if ($box->type === \common\models\box\Box::TYPE_FREE && !empty($getNextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate())): ?>
                           <div class="timerNextOpenFreeBoxDate">
                               <p><?=Yii::t('common',  'Открытие кейса недоступно, так как вы недавно уже открывали бесплатный кейс.')?></p>
                               <?= \common\components\widgets\TimerWidget::widget([
                                   'deadline' => $getNextOpenFreeBoxDate,
                                   'timerId' => 'nextOpenFreeBoxDate',
                                   'text' => Yii::t('common',  'Кейс будет доступен через:'),
                               ]); ?>
                           </div>
                        <?php else: ?>
                            <div class="box_entity_card_actions">
                                <a class="box_entity_card_actions_btn<?=($box->getPriceFinal() <= 0) ? ' box_entity_card_actions_btn_free' : '' ?>" href="#">
                                    <?=Yii::t('common', 'Открыть контейнер')?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php if ($box->getPriceFinal() <= 0): ?>
                                            <?=Yii::t('common', 'Бесплатно')?>
                                        <?php else: ?>
                                            <?=$box->getPriceFinal()?> <span class="currency"><?=$box->currency?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                                <div class="box_entity_card_actions_inventory_action" style="display: none">
                                    <a class="btn box_entity_card_actions_inventory_btn" href="/user/inventory">
                                        <?=Yii::t('common', 'Открыть инвентарь')?>
                                    </a>
                                    <p><?=Yii::t('common',  'Следующий бесплатный контейнер будет доступен через 24 часа')?></p>
                                </div>
                            </div>
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
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="box_content_wrapper">
                <h2><?=Yii::t('common', 'Содержание кейса')?></h2>
                <div class="box_content">
                    <?php foreach ($box->boxDrop as $boxDrop): ?>
                        <a href="/market/view?id=<?= $boxDrop->drop->id ?>" class="box_content_card<?=' drop_card level' . $boxDrop->drop->getLevel()?>">
                            <div class="box_content_card_info">
                                <div class="box_content_card_info_type"><?=Yii::t('database', $boxDrop->drop->type->name)?></div>
                                <div class="box_content_card_info_title"><?=$boxDrop->drop->getShortName()?></div>
                            </div>
                            <div class="box_content_card_image">
                                <img src="<?= $boxDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $boxDrop->drop->name)?>">
                            </div>
                        </a>
                    <?php endforeach; ?>
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
                    <p><?=Yii::t('common', 'С вашего баланса будет списано')?> <?=$box->getPriceFinal()?> <span class="currency"><?=$box->currency?></span>.</p>
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
    <div class="modal modal-alert fade" id="notBalanceModal" tabindex="-1" aria-labelledby="notBalanceModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"><?=Yii::t('common', 'Внимание!')?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?=Yii::t('common', 'На вашем балансе недостаточно средств')?>.</p>
                    <a href="/user/payment" class="btn"><?=Yii::t('common', 'Пополнить')?></a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php

use yii\web\View;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Корзина") . " - {$user->userProfile->name}";

/** @var UserDrop[] $userDrops */
$userDrops = $user->getUserDrop()
    ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
    ->all();
?>

<?php
$lang = substr(Yii::$app->language, 0, 2);
$this->registerJs(<<<JS
        var blocked_products = $('.blocked_products_timer');
        for (var i = 0; i < blocked_products.length; i++) {
            var dateTime = $(blocked_products[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(blocked_products[i]).html(left.locale('{$lang}').fromNow());
        }
JS
);
?>
<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?= $this->render('@frontend/views/widgets/_profile'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="profile_content">
                    <div class="profile_content_header">
                        <?=Yii::t('common', "Корзина")?>
                    </div>
                    <div class="profile_content_body">
                        <?= Alert::widget() ?>
                        <?php if (!empty($userDrops)):?>
                            <div class="box_cards_wrapper">
                                <div class="box_cards">
                                    <?php foreach ($userDrops as $userDrop): ?>
                                        <?php foreach ($userDrop->drop as $drop): ?>
                                            <?php $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time(); ?>
                                            <div class="box_cards_card">
                                                <div class="box_cards_card_info">
                                                    <div class="box_cards_card_info_title"><?=Yii::t('database', $drop->name)?></div>
                                                </div>
                                                <div class="box_cards_card_image">
                                                    <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>">
                                                </div>
                                                <div class="box_cards_card_count">
                                                    x<?= $userDrop->count ?>
                                                </div>
                                                <?php if ($blocked): ?>
                                                    <div class="box_cards_card_blocked_wrap">
                                                        <div class="box_cards_card_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
                                                        <div class="box_cards_card_blocked_timer blocked_products_timer" data-time="<?=strtotime($drop->blocked_at)?>"><?=$drop->blocked_at?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php $form = ActiveForm::begin(); ?>
                                                <input type="hidden" name="sell" value="<?=$userDrop->id?>"/>
                                                <?php if (empty($userDrop->box_id) && empty($userDrop->sets_id)): ?>
                                                    <button type="submit" class="btn box_cards_card_btn" data-bs-dismiss="modal">
                                                        <?=Yii::t('common', 'Вернуть')?>
                                                        <span class="badge bg-danger">+<?=$drop->getRealPrice()?></span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn box_cards_card_btn disabled" disabled>
                                                        <?=Yii::t('common', 'Нет возврата')?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php ActiveForm::end(); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else:?>
                            <p class="mt-4">
                                <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
                            </p>
                        <?php endif;?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
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
    ->orderBy(['id' => SORT_DESC])
    ->all();

\frontend\assets\LauncherAsset::register($this);
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
<div class="store_launcher">
    <?= Alert::widget() ?>
    <?php if (!empty($userDrops)):?>
        <div class="store_launcher_cards">
            <?php foreach ($userDrops as $userDrop): ?>
                <?php foreach ($userDrop->drop as $drop): ?>
                    <?php $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time(); ?>
                    <div class="store_launcher_cards_item_wrap">
                        <div class="store_launcher_cards_item" data-id="<?=$userDrop->id?>">
                            <div class="store_launcher_cards_item_image">
                                <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>">
                            </div>
    <!--                        <div class="store_launcher_cards_item_title">--><?php //echo Yii::t('database', $drop->name)?><!--</div>-->
                            <?php if ($userDrop->count > 1): ?>
                            <div class="store_launcher_cards_item_count">
                                x<?= $userDrop->count ?>
                            </div>
                            <?php endif; ?>
                            <div class="store_launcher_cards_item_button<?=$blocked ? ' blocked' : ''?>">
                                <?php if ($blocked): ?>
                                    <?=Yii::t('common', 'Недоступно')?>
                                <?php else: ?>
                                    <?=Yii::t('common', 'Получить')?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($blocked): ?>
                            <div class="store_launcher_cards_item_blocked_wrap">
                                <div class="store_launcher_cards_item_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
                                <div class="store_launcher_cards_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($drop->blocked_at)?>"><?=$drop->blocked_at?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php else:?>
        <p class="mt-4">
            <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
        </p>
    <?php endif;?>
</div>
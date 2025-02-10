<?php

use yii\web\View;
use common\models\user\UserDrop;
use common\models\box\DropBlocked;
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

/** @var \common\models\box\Category[] $categories */
$categories = \common\models\box\Category::find()
                                         ->orderBy(['sort' => SORT_ASC])
                                         ->all();

$this->registerJs(<<<JS
    var categories = $('.store_launcher_categories .store_launcher_categories_category');
    window.currentCategoryId = '';
    window.search = function() {
        var input, filter, ul, li, a, i, txtValue, categoryId;
        ul = document.getElementById("products");
        li = ul.querySelectorAll(".store_launcher_cards_item_wrap");
        for (i = 0; i < li.length; i++) {
            txtValue = $(li[i]).attr('data-title');
            categoryId = $(li[i]).attr('data-category-id');
            if ( (currentCategoryId === '' || currentCategoryId === undefined || categoryId == currentCategoryId)) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }
    categories.click(function () {
        if ($(this).hasClass('active')) {
            window.currentCategoryId = '';
            $(this).removeClass('active');
            search();
            return;
        }
        var categories = $('.store_launcher_categories .store_launcher_categories_category.active');
        categories.removeClass('active');
        $(this).addClass('active');
        window.currentCategoryId = $(this).attr('data-id');
        search();
    });
JS
);
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
    <h1><?=Yii::t('common', 'Корзина сервера')?></h1>
    <p><?=Yii::t('common', 'Это ваша корзина с покупками, вы можете забрать их в любой момент')?></p>
    <?php if (!empty($user->server) && ($user->server->is_store || $user->store)): ?>
        <?php if (!empty($userDrops)):?>
            <div class="store_launcher_categories">
                <?php foreach ($categories as $category): ?>
                    <?php if ($category->id === 1) continue; ?>
                    <div class="store_launcher_categories_category" data-id="<?=$category->id?>">
                        <div class="store_launcher_categories_category_name"><?=Yii::t('database', $category->name)?></div>
                        <div class="store_launcher_categories_category_image" style="background-image: url('<?=$category->image?>');"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="store_launcher_cards" id="products">
                <?php $serverId = $user->server->id; ?>
                <?php foreach ($userDrops as $userDrop): ?>
                    <?php foreach ($userDrop->drop as $drop): ?>
                        <?php $blockedAt = DropBlocked::getBlocked($drop->id, $serverId); ?>
                        <?php $blocked = !empty($blockedAt); ?>
                        <div class="store_launcher_cards_item_wrap" data-category-id="<?=$userDrop->drop[0]->category_id?>">
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
                                    <div class="store_launcher_cards_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($blockedAt)?>"><?=$blockedAt?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php else:?>
            <div class="content_text">
                <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
            </div>
        <?php endif;?>
    <?php else: ?>
        <div class="content_text">
            <?=Yii::t('common', 'Магазин на сервере котором вы находитесь, недоступен!')?>
        </div>
    <?php endif;?>
</div>
<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

use yii\bootstrap5\Html;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\box\Select;

$this->title = Yii::t('common', 'Простой проект серверов для Rust');

\frontend\assets\LastDropAsset::register($this);
\frontend\assets\UserBoxAsset::register($this);

$this->registerJs(<<<JS
    $(document).on('pjax:send', function() {
       $('#product-loader').addClass('active');
       $('#buy_product').prop('disabled', true);
    });
    $(document).on('pjax:complete', function() {
      $('#product-loader').removeClass('active');
      updateBalance();
    });
    var categories = $('.products_categories .products_categories_category');
    var categoriesRight = $('.products_wrap_wrap > .categories .categories_item');
    window.currentCategoryId = categories.first().attr('data-id');
    categories.first().addClass('products_categories_category_active');
    categoriesRight.first().addClass('categories_item_active');
    window.search = function() {
        var input, filter, ul, li, a, i, txtValue, categoryId;
        input = document.getElementById("search");
        filter = input.value.toUpperCase();
        ul = document.getElementById("products");
        li = ul.querySelectorAll("#products .products_item");
        for (i = 0; i < li.length; i++) {
            txtValue = $(li[i]).attr('data-title');
            categoryId = $(li[i]).attr('data-category-id');
            if (txtValue.toUpperCase().indexOf(filter) > -1 && (currentCategoryId === '' || currentCategoryId === undefined || categoryId == currentCategoryId)) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }
    categories.click(function () {
        var categories = $('.products_categories .products_categories_category.products_categories_category_active');
        categories.removeClass('products_categories_category_active');
        $(this).addClass('products_categories_category_active');
        window.currentCategoryId = $(this).attr('data-id');
        $('#search').val('');
        search();
    });
    categoriesRight.click(function () {
        var categories = $('.products_wrap_wrap > .categories .categories_item.categories_item_active');
        categories.removeClass('categories_item_active');
        $(this).addClass('categories_item_active');
        window.currentCategoryId = $(this).attr('data-id');
        $('#search').val('');
        search();
    });
JS
);

/** @var \common\models\box\Category[] $categories */
$categories = \common\models\box\Category::find()
    ->cache(3600)
    ->all();
?>
<?php
$locale = substr(Yii::$app->language, 0, 2);
$this->registerJs(<<<JS
        $(document).on('change', '.modal_form_product_select_item_radio', function (e) {
            $('.modal_form_product_buy').val('0');
            $('#buy_product').submit();
            return false;
        });
        var blocked_products = $('.blocked_products_timer');
        for (var i = 0; i < blocked_products.length; i++) {
            var dateTime = $(blocked_products[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(blocked_products[i]).html(left.locale('{$locale}').fromNow());
        }
JS
);
?>


<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?php echo $this->render('@frontend/views/widgets/_alert'); ?>
            <?php // $this->render('@frontend/views/widgets/_buttons'); ?>
            <?= $this->render('@frontend/views/widgets/_skindrops'); ?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_top'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
<!--            --><?php //echo $this->render('@frontend/views/widgets/_bonuses'); ?>
<!--            --><?php //echo $this->render('@frontend/views/widgets/_banners'); ?>
        </aside>
        <main id="main" role="main" class="products">
            <div class="main_child main_index">
                <div class="products_search">
                    <div class="products_search_icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <input type="text" id="search" onkeyup="search()" placeholder="<?=Yii::t('common', 'Введите название предмета..')?>" title="<?=Yii::t('common', 'Поиск по товарам сервера')?>" class="form-control" autocomplete="off">
                </div>
                <div class="products_categories">
                    <ul>
                        <li>
                            <div class="products_categories_category" data-id="">
                                <?=Yii::t('common', 'Все')?>
                            </div>
                        </li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <div class="products_categories_category" data-id="<?=$category->id?>">
                                    <?=Yii::t('database', $category->name)?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="products_wrap_wrap">
                    <div class="products_wrap">
                        <?php if (!Yii::$app->user->isGuest): ?>
                        <div class="products_buttons">
                            <?php if (!empty($getNextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate())): ?>
                                <div class="products_item_roulete products_item_roulete_blocked" data-title="<?=Yii::t('common', 'Ежедневный крейт')?>">
                                    <div class="products_item_roulete_blocked_title">
                                        <i class="far fa-clock"></i>
                                        <?=Yii::t('common', 'Ежедневная награда будет доступна')?> <span class="products_item_roulete_blocked_title_timer" id="roulete_timer"><?=Yii::t('common', 'через 18 часов')?></span>
                                    </div>
                                </div>
                                <?php
                                $lang = substr(Yii::$app->language, 0, 2);
                                $unixDate = strtotime($getNextOpenFreeBoxDate);
                                $this->registerJs(<<<JS
                                    var dateRoulete = {$unixDate};
                                    var left = moment.unix(dateRoulete);
                                    $('#roulete_timer').html(left.locale('{$lang}').fromNow());
                                JS
                                );?>
                            <?php else: ?>
                                <div data-href="/servers/bonus" class="products_item_roulete show-modal-link" data-size="modal-lg" data-toggl="modal" data-target="modal-dialog" data-title="<?=Yii::t('common', 'Ежедневная награда')?>">
                                    <div class="products_buttons_wipe_block_title"><?=Yii::t('common', 'Ежедневная награда')?></div>
                                </div>
                            <?php endif; ?>
                            <div data-href="/servers/wipe-block" class="products_buttons_wipe_block show-modal-link" data-size="modal-lg" data-toggl="modal" data-target="modal-dialog" data-title="<?=Yii::t('common', 'Вайп блок')?>">
                                <div class="products_buttons_wipe_block_title"><?=Yii::t('common', 'Вайп блок')?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="products" id="products">
                            <?php if ($this->beginCache('products' . Yii::$app->language, ['duration' => 300])): ?>
                                <?php foreach (\common\models\box\Sets::getSetsForMarket() as $sets): ?>
                                    <div data-href="/market/form-modal-set?id=<?=$sets->id?>" data-category-id="1" class="products_item show-modal-link active" data-title="<?=Yii::t('database', $sets->name)?>" data-size="modal" data-toggl="modal" data-target="modal-dialog">
                                        <div class="products_item_body">
                                            <div class="products_item_image">
                                                <img src="<?= $sets->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $sets->name)?>" width="100px">
                                            </div>
                                            <div class="products_item_price">
                                                <?php if ($sets->discount > 0 && ceil($sets->price) != $sets->getRealPrice()): ?>
                                                    <span class="products_item_price_old"><?=ceil($sets->price)?></span>
                                                <?php endif; ?>
                                                <span class="products_item_price_current"><?=$sets->getRealPrice()?></span>
                                                <span class="products_item_price_currency">RUB</span>
                                            </div>
                                        </div>
                                        <div class="products_item_body_hover">
                                            <div class="products_item_title">
                                                <span><?=Yii::t('database', $sets->name)?></span>
                                            </div>
                                            <div class="products_item_title">
                                                <span class="products_item_price_current"><?=$sets->getRealPrice()?></span>
                                                <span class="products_item_price_currency">RUB</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach (Select::getForMarket() as $item): ?>
                                    <div data-href="/market/form-modal-select?id=<?=$item->id?>" data-category-id="1" class="products_item show-modal-link active" data-title="<?=Yii::t('database', $item->name)?>" data-size="modal-sm" data-toggl="modal" data-target="modal-dialog">
                                        <div class="products_item_body">
                                            <div class="products_item_image">
                                                <img src="<?= $item->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $item->name)?>" width="100px">
                                            </div>
                                        </div>
                                        <div class="products_item_body_hover">
                                            <div class="products_item_title">
                                                <span><?=Yii::t('database', $item->name)?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach (Drop::getForMarket() as $drop): ?>
                                    <?php
                                        $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time();
                                        if ($blocked) {
                                            continue;
                                        }
                                    ?>
                                    <?= $this->render('_product', [
                                            'drop' => $drop
                                    ]); ?>
                                <?php endforeach; ?>
                                <?php foreach (Drop::getForMarket() as $drop): ?>
                                    <?php
                                    $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time();
                                    if (!$blocked) {
                                        continue;
                                    }
                                    ?>
                                    <?= $this->render('_product', [
                                        'drop' => $drop
                                    ]); ?>
                                <?php endforeach; ?>
                                <?php $this->endCache(); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="categories">
                        <ul>
                            <li>
                                <div class="categories_item" data-id="">
                                    <?=Yii::t('common', 'Все')?>
                                </div>
                            </li>
                            <?php foreach ($categories as $category): ?>
                                <li>
                                    <div class="categories_item" data-id="<?=$category->id?>">
                                        <?=Yii::t('database', $category->name)?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="last_drops_wrapper">
    <?php if ($this->beginCache('_last_drops' . Yii::$app->language, ['duration' => 10])): ?>
        <?= $this->render('@frontend/views/widgets/_last_drops'); ?>
        <?php $this->endCache(); ?>
    <?php endif; ?>
</div>
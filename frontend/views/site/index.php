<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

use common\models\statistics\Statistics;
use yii\bootstrap5\Html;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\box\Select;

$this->title = Yii::t('common', Yii::$app->params['title']);

\frontend\assets\LastDropAsset::register($this);
\frontend\assets\UserBoxAsset::register($this);

$this->registerJs(<<<JS
    $(document).on('pjax:send', function() {
       $('#product-loader').addClass('active');
       $('#buy_product').attr('aria-disabled', true);
    });
    $(document).on('pjax:complete', function() {
       $('#product-loader').removeClass('active');
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
    ->andWhere(['show_main_block' => true])
    ->cache(60)
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
//$getNextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate();
$userData = [];
$awards = [];
$projectStats = Statistics::projectStats();
$userStats = null;
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
    $userData = [
        'username' => $user->username,
        'steam_id' => $user->steam_id,
    ];
    if (!empty($user->server)) {
        $userStats = Statistics::find()
                               ->andWhere(['steam_id' => $user->steam_id])
                               ->andWhere(['server_tag' => $user->server->tag])
                               ->andWhere(['wipe' => $user->server->currentWipe()])
                               ->indexBy('key')
                               ->cache(60)
                               ->all();
    }
    $awards = \common\models\tasks\Task::awards($user->id);
}

?>

<?php //if (!empty($getNextOpenFreeBoxDate)) {
//    $lang = substr(Yii::$app->language, 0, 2);
//    $unixDate = strtotime($getNextOpenFreeBoxDate);
//    $this->registerJs(<<<JS
//                        var dateRoulete = {$unixDate};
//                        var left = moment.unix(dateRoulete);
//                        $('#roulete_timer').html(left.locale('{$lang}').fromNow());
//                JS
//    );
//} ?>
<?=Yii::$app->view->render('index.twig', [
//    'ROULETTE_ACCESS' => !empty($getNextOpenFreeBoxDate),
    'USER_GUEST' => Yii::$app->user->isGuest,
    'PRODUCTS_MAIN_BLOCK' => Yii::$app->view->render('products_main.twig', [
        'PRODUCT_DROPS_SETS' => \common\models\box\Sets::getSetsForMarket(true),
        'PRODUCT_DROPS_SELECT' => Select::getForMarket(true),
        'PRODUCT_DROPS' => Drop::getForMarket(true),
    ]),
    'PRODUCTS' => Yii::$app->view->render('products.twig', [
        'PRODUCT_DROPS_SETS' => \common\models\box\Sets::getSetsForMarket(),
        'PRODUCT_DROPS_SELECT' => Select::getForMarket(),
        'PRODUCT_DROPS' => Drop::getForMarket(),
    ]),
    'CATEGORIES' => Yii::$app->view->render('categories.twig', [
        'ITEMS' => $categories,
    ]),
    'STATISTICS' => Yii::$app->view->render('statistics.twig', [
        'USER_GUEST' => Yii::$app->user->isGuest,
        'PROJECT_STATS' => $projectStats,
        'USER' => $userData,
        'BOT_LINK' => "https://t.me/" . Yii::$app->params['tgPersonalBot'],
        'USER_STATS' => $userStats,
        'STATS' => new Statistics(),
        'AWARDS' => $awards,
    ]),
]);?>


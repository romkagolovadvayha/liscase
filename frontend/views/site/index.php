<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */
/** @var \common\models\blog\Blog[] $latestPosts */

use common\models\statistics\Statistics;
use yii\bootstrap5\Html;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\box\Select;
use common\models\servers\Servers;

$this->title = Yii::t('database', Yii::$app->settings->get('site_title'));

\frontend\assets\LastDropAsset::register($this);

/** @var \common\models\box\Category[] $categories */
$categories = \common\models\box\Category::getCategories(true);

// Ensure latestPosts is defined
if (!isset($latestPosts)) {
    $latestPosts = [];
}
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
    $awards = \common\models\tasks\Task::awards($user->id, false);
}

$servers = Servers::find()
                  ->cache(30)
                  ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                  ->orderBy(['sort' => SORT_ASC])
                  ->all();
$userData['SERVER_ACTIVE_ID'] = $servers[0]->id;
$userData['SERVER_ACTIVE_TAG'] = $servers[0]->tag;
$userData['USER_STATS_LINK'] = null;
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
    if (!empty($user->server)) {
        foreach ($servers as $server) {
            if ($server->id == $user->server->id) {
                $userData['SERVER_ACTIVE_ID'] = $user->server->id;
                $userData['SERVER_ACTIVE_TAG'] = $user->server->tag;
                $userData['USER_STATS_LINK'] = $user->getLink('stats');
                break;
            }
        }
    }
}
$images = Drop::productsImages();
$SETTINGS = Yii::$app->settings;
$canonical = Yii::$app->params['homePage'];
$this->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);
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
        'PRODUCT_DROPS' => Drop::getForMarket(true),
        'IMAGES' => $images,
        'SETTINGS' => $SETTINGS,
    ]),
    'PRODUCTS' => Yii::$app->view->render('products.twig', [
        'PRODUCT_DROPS' => Drop::getForMarket(),
        'IMAGES' => $images,
        'SETTINGS' => $SETTINGS,
    ]),
    'CATEGORIES' => Yii::$app->view->render('categories.twig', [
        'ITEMS' => $categories,
        'SETTINGS' => $SETTINGS,
    ]),
    'STATISTICS' => Yii::$app->view->render('statistics.twig', [
        'USER_GUEST' => Yii::$app->user->isGuest,
        'PROJECT_STATS' => $projectStats,
        'USER' => $userData,
        'BOT_LINK' => "https://t.me/" . Yii::$app->settings->get('tgbot_login'),
        'USER_STATS' => $userStats,
        'STATS' => new Statistics(),
        'AWARDS' => $awards,
        'SETTINGS' => $SETTINGS,
    ]),
    'latestPosts' => $latestPosts ?? [],
    'SETTINGS' => $SETTINGS,
    'LOGO' => Yii::$app->settings->get('design_logo'),
    'PROJECT_NAME' => Yii::$app->settings->get('site_project_name'),
    'VK' => Yii::$app->settings->get('social_vk'),
    'DISCORD' => Yii::$app->settings->get('social_discord'),
    'TELEGRAM_BOT' => Yii::$app->settings->get('social_telegram'),
    'EMAIL' => Yii::$app->settings->get('site_email'),
    'URL' => Yii::$app->params['homePage']
]);?>


<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\widgets\Alert;
use common\models\blog\BlogCategory;
use common\components\web\LanguagePicker;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use common\models\user\UserBalance;

AppAsset::register($this);
\frontend\assets\OnlineCounterAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => '/images/favicon.svg']);

$rightMenu = [];
if (!Yii::$app->user->isGuest) {
    $rightMenu[] = [
        'label'   => Html::img(Yii::$app->user->identity->userProfile->avatar, ['width' => '24px']),
        'visible' => !Yii::$app->user->isGuest,
        'encode'  => false,
        'items'   => [
            [
                'label'   => Yii::t('common', 'АДМИНКА'),
                'url'     => Yii::$app->params['backendUrl'],
                'visible' => Yii::$app->user->identity && Yii::$app->user->identity->isAccessBackend(),
            ],
            [
                'label' => Yii::t('common', 'Профиль'),
                'url'   => '/users/' . Yii::$app->user->identity->username,
            ],
            [
                'label' => Yii::t('common', 'Выйти'),
                'url'   => '/site/logout',
            ],
        ],
    ];
} else {
    $rightMenu[] = [
        'label'   => '<svg class="header_nav_right_icon" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 392.581 392.581" xml:space="preserve">
<g>
	<g>
		<path d="M16.984,179.318C7.62,179.318,0,186.938,0,196.303c0,9.353,7.614,16.973,16.984,16.973h71.499v-33.958H16.984z"/>
		<path d="M335.992,44.252H145.091c-31.213,0-56.607,25.382-56.607,56.589v1.771v76.699h152.969l-32.714-32.717
			c-6.611-6.623-6.611-17.396,0-24.016c3.207-3.207,7.464-4.975,11.998-4.975c4.551,0,8.809,1.769,12.021,4.975l61.705,61.708
			c1.549,1.543,2.774,3.372,3.65,5.443c0.907,2.23,1.327,4.383,1.327,6.572c0,2.183-0.414,4.339-1.261,6.403
			c-0.906,2.181-2.132,4-3.669,5.567l-61.753,61.741c-3.225,3.206-7.494,4.966-12.021,4.966c-4.528,0-8.81-1.76-12.004-4.966
			c-6.611-6.636-6.611-17.408,0-24.025l32.72-32.714H88.483v18.572v34.102v25.803c0,31.194,25.395,56.577,56.607,56.577h190.901
			c31.213,0,56.589-25.383,56.589-56.577V100.848C392.581,69.634,367.205,44.252,335.992,44.252z"/>
	</g>
</g>
</svg>',
        'visible' => Yii::$app->user->isGuest,
        'url'     => '/auth/login',
        'encode'  => false,
    ];
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <meta name="robots" content="noindex, nofollow"/>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header class="header">
    <div class="header_start_wrapper">
        <div class="header_start container">
            <div class="header_start_left">
                <a class="header_start_left_menu" id="toggle_header_menu" href="#">
                    <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" class="header_start_left_menu_icon">
                        <path d="M19 4a1 1 0 01-1 1H2a1 1 0 010-2h16a1 1 0 011 1zm0 6a1 1 0 01-1 1H2a1 1 0 110-2h16a1 1 0 011 1zm-1 7a1 1 0 100-2H2a1 1 0 100 2h16z"/>
                    </svg>
                </a>
                <a class="header_start_left_logo" href="<?=Yii::$app->homeUrl?>">
                    <span><?=\common\models\settings\Settings::getByKey('title_short')?></span>
                </a>
                <div class="header_start_left_online_counter" title="<?=Yii::t('common', 'Пользователей онлайн')?>">
                    <span id="online_counter"><?=$this->render('../widgets/_online_counter')?></span>
                </div>
                <?=Nav::widget(['items' => $rightMenu, 'options' => ['class' =>'header_auth_menu']])?>
            </div>
            <div class="header_start_right">
                <div class="header_start_right_language">
                    <?=LanguagePicker::widget([
                        'languages'  => [
                            'en-US' => 'EN',
                            'ru-RU' => 'RU',
//                            'de-DE' => 'DE',
//                            'uk-UA' => 'UK',
//                            'es-ES' => 'ES',
                        ],
                        'skin' => LanguagePicker::SKIN_DROPDOWN,
                        'size' => LanguagePicker::SIZE_LARGE,
                    ])?>
                </div>
            </div>
        </div>
    </div>
    <nav class="header_nav container" id="header_menu">
        <div class="header_nav_left">
            <?=$this->render('_header_menu_categories')?>
        </div>
        <div class="header_nav_right">
            <?=Nav::widget(['items' => $rightMenu, 'options' => ['class' =>'header_auth_menu']])?>
        </div>
    </nav>
</header>

<?= $content ?>

<footer id="footer" class="mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">&copy; <?=\common\models\settings\Settings::getByKey('title_short')?> <?= date('Y') ?></div>
            <div class="col-md-6 text-center text-md-end"></div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

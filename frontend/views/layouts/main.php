<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use common\components\web\LanguagePicker;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use common\models\user\UserBalance;

AppAsset::register($this);
\frontend\assets\OnlineCounterAsset::register($this);
if (!Yii::$app->user->isGuest) {
    \frontend\assets\BalanceAsset::register($this);
}

\frontend\assets\ModalAsset::register($this);
\common\assets\SlickCarouselAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => '/images/favicon.svg']);

$rightMenu = [
    [
        'label'   => '<span class="auth__text">'.Yii::t('common', 'Войти через Steam').'</span>',
        'url'     => '/auth/oauth?authclient=steam',
        'encode' => false,
        'options'     => [
            'class' => 'menu-login'
        ],
        'visible' => Yii::$app->user->isGuest,
    ]
];
$mobileMenu = [
    [
        'label'   => '<i class="fab fa-steam-square"></i> ' . Yii::t('common', 'Войти через Steam'),
        'url'     => '/auth/oauth?authclient=steam',
        'encode' => false,
        'visible' => Yii::$app->user->isGuest,
    ]
];

if (!Yii::$app->user->isGuest) {
    $balanceStr = Yii::$app->user->identity->getPersonalBalance()->getBalanceFormat();
    $balance = Yii::$app->user->identity->getPersonalBalance()->balanceCeil;
    $this->registerJs(<<<JS
    var balanceStr = '{$balanceStr}';
    var balance = {$balance};
JS
        , \yii\web\View::POS_BEGIN);
    $rightMenu[] = [
        'label'   => '<div class="profile_header">
                            <div class="balance-item">
                                    <div class="name">' . Yii::$app->user->identity->userProfile->name . '</div>
                                    <div class="balance">
                                        <span class="currency">' . UserBalance::getCurrency() . '</span>
                                        <span class="balance_count">' . $balanceStr . '</span>
                                    </div>
                            </div>
                            ' . Html::img(Yii::$app->user->identity->userProfile->avatar, ['width' => '40px']) . '
                      </div>',
        'visible' => !Yii::$app->user->isGuest,
        'encode' => false,
        'items' => [
            [
                'label'   => '<i class="fas fa-shopping-basket"></i> ' . Yii::t('common', 'Корзина'),
                'encode' => false,
                'url'     => '/user/inventory',
            ],
            [
                'label'   => '<i class="fas fa-history"></i> ' . Yii::t('common', 'История операций'),
                'encode' => false,
                'url'     => '/user/history',
            ],
            [
                'label'   => '<i class="far fa-calendar-check"></i> ' . Yii::t('common', "Задания на вайп"),
                'encode' => false,
                'url'     => '/user/tasks',
            ],
            [
                'label'   => '<i class="fas fa-wallet"></i> ' . Yii::t('common', 'Пополнить баланс'),
                'encode' => false,
                'linkOptions' => [
                        'class' => 'show-modal-link',
                        'data-title' => 'Пополнить баланс',
                        'data-size' => 'modal-sm',
                        'data-toggl' => 'modal',
                        'data-href' => '/user/payment',
                        'data-target' => 'modal-dialog',
                ],
                'url'     => '#',
            ],
            [
                'label'   => '<i class="fas fa-users-cog"></i> ' . Yii::t('common', 'АДМИНКА'),
                'encode' => false,
                'url'     => Yii::$app->params['backendUrl'],
                'visible' => Yii::$app->user->identity && Yii::$app->user->identity->isAccessBackend(),
            ],
            [
                'label'  => '<i class="fas fa-sign-out-alt"></i> ' . Yii::t('common', 'Выйти'),
                'encode' => false,
                'url'    => '/user/logout',
            ],
        ],
    ];
    $mobileMenu[] = [
        'label'   => '<i class="fas fa-shopping-basket"></i> ' . Yii::t('common', 'Корзина'),
        'encode' => false,
        'url'     => '/user/inventory',
    ];
    $mobileMenu[] = [
        'label'   => '<i class="fas fa-history"></i> ' . Yii::t('common', 'История операций'),
        'encode' => false,
        'url'     => '/user/history',
    ];
    $mobileMenu[] = [
        'label'   => '<i class="far fa-calendar-check"></i> ' . Yii::t('common', "Задания на вайп"),
        'encode' => false,
        'url'     => '/user/tasks',
    ];
    $mobileMenu[] = [
        'label'   => '<i class="fas fa-wallet"></i> ' . Yii::t('common', 'Пополнить баланс'),
        'encode' => false,
        'linkOptions' => [
            'class' => 'show-modal-link',
            'data-title' => 'Пополнить баланс',
            'data-size' => 'modal-sm',
            'data-toggl' => 'modal',
            'data-href' => '/user/payment',
            'data-target' => 'modal-dialog',
        ],
        'url'     => '#',
    ];
}
$mobileMenu[] = [
    'label'   => '<i class="fas fa-gamepad"></i> ' . Yii::t('common', 'Наши сервера'),
    'encode' => false,
    'url'     => '/servers',
];
$mobileMenu[] = [
    'label'   => '<i class="fas fa-chart-pie"></i> ' . Yii::t('common', 'Статистика'),
    'encode' => false,
    'url'     => '/stats?server=max3',
];
$mobileMenu[] = [
    'label'   => '<i class="fab fa-discord"></i> ' . Yii::t('common', 'Мы в Discord'),
    'encode' => false,
    'url'     => 'https://discord.gg/prostoj',
    'linkOptions'     => ['target' => '_blank'],
];
$mobileMenu[] = [
    'label'   => '<i class="fab fa-vk"></i> ' . Yii::t('common', 'Мы в Вконтакте'),
    'encode' => false,
    'url'     => 'https://vk.com/prostoj_rust',
    'linkOptions'     => ['target' => '_blank'],
];
if (!Yii::$app->user->isGuest) {
    $mobileMenu[] = [
        'label'   => '<i class="fas fa-users-cog"></i> ' . Yii::t('common', 'АДМИНКА'),
        'encode' => false,
        'url'     => Yii::$app->params['backendUrl'],
        'visible' => Yii::$app->user->identity && Yii::$app->user->identity->isAccessBackend(),
    ];
    $mobileMenu[] = [
        'label'  => '<i class="fas fa-sign-out-alt"></i> ' . Yii::t('common', 'Выйти'),
        'encode' => false,
        'url'    => '/user/logout',
    ];
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <?php $this->head() ?>
    <meta name="robots" content="noindex, nofollow"/>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
    <nav class="navbar-expand-md navbar-dark bg-dark navbar">
        <div class="container-fluid">
            <div class="header_mobile">
                <a class="navbar-brand" href="<?=Yii::$app->homeUrl?>">
                    <img src="/images/logo.png"/>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarHeader" aria-controls="navbarHeader" aria-expanded="false" aria-label="Меню сайта">
                    <?php if (!Yii::$app->user->isGuest): ?>
                    <div class="profile_header">
                        <div class="balance-item">
                            <div class="name"><?=Yii::$app->user->identity->userProfile->name?></div>
                            <div class="balance">
                                <span class="currency"><?=UserBalance::getCurrency()?></span>
                                <span class="balance_count"><?=$balanceStr?></span>
                            </div>
                        </div>
                        <?=Html::img(Yii::$app->user->identity->userProfile->avatar, ['width' => '40px'])?>
                        <div class="bars_icon">
                            <i class="fas fa-bars"></i>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="bars_icon">
                            <i class="fas fa-bars"></i>
                        </div>
                    <?php endif; ?>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbarHeader">
                <a class="navbar-brand" href="<?=Yii::$app->homeUrl?>">
                    <img src="/images/logo.png"/>
                </a>

<!--                <div class="header-language-picker" style="display: none">-->
<!--                    --><?php //echo LanguagePicker::widget([
//                        'languages'  => [
//                            'en-US' => 'EN',
//                            'ru-RU' => 'RU',
//                            'de-DE' => 'DE',
//                            'uk-UA' => 'UK',
//                            'es-ES' => 'ES',
//                        ],
//                        'skin' => LanguagePicker::SKIN_DROPDOWN,
//                        'size' => LanguagePicker::SIZE_LARGE,
//                    ])?>
<!--                </div>-->
                <?=Nav::widget([
                    'items' => [
                        [
                            'label'   => '<div class="header__online-counter">
                                                ' . Html::img('/images/icons/online.svg', ['width' => '27px', 'class' => 'header__online-icon']) . '
                                                <div class="header__online">
                                                    <div class="header__online-cnt online_counter">' . $this->render('@frontend/views/widgets/_online_counter') . '</div>
                                                    <div class="header__online-label">'.Yii::t('common', 'Онлайн').'</div>
                                                </div>
                                           </div>',
                            'encode' => false,
                            'options'     => [
                                'class' => 'menu_online_counter'
                            ],
                        ],
                        [
                            'label'   => '<i class="fas fa-tasks"></i> Правила',
                            'url'     => '/servers/rules?server=max3',
                            'encode' => false,
                            'options'     => [
                                'class' => 'menu-rules'
                            ],
                        ],
//                        [
//                            'label'   => '<i class="far fa-comment-dots"></i> Как получать скины',
//                            'url'     => '/skindrops',
//                            'encode' => false,
//                            'options'     => [
//                                'class' => 'menu-faq'
//                            ],
//                        ],
                    ],
                    'options' => ['class' =>'navbar-nav me-auto mb-2 mb-lg-0 header-left-menu'],
                ]);
                ?>
                <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
                <?=Nav::widget([
                    'items' => [
                        [
                            'label'   => '<i class="fab fa-vk"></i>',
                            'encode' => false,
                            'options' => ['class' =>'vk_social', 'title' => 'Мы в Вконтакте'],
                            'url'     => 'https://vk.com/prostoj_rust',
                            'linkOptions'     => ['target' => '_blank'],
                        ],
                        [
                            'label'   => '<i class="fab fa-discord"></i>',
                            'encode' => false,
                            'options' => ['class' =>'discord_social', 'title' => 'Мы в Discord'],
                            'url'     => 'https://discord.gg/prostoj',
                            'linkOptions'     => ['target' => '_blank'],
                        ],
                    ],
                    'options' => ['class' =>'navbar-nav nav-pills header-social-menu'],
                ]);
                ?>
                <?=Nav::widget([
                                   'items' => $mobileMenu,
                                   'options' => ['class' =>'header-mobile-menu'],
                               ]);
                ?>
                <?=Nav::widget([
                    'items' => $rightMenu,
                    'options' => ['class' =>'navbar-nav nav-pills header-right-menu'],
                ]);
                ?>
            </div>
        </div>
    </nav>
</header>

<?php if (!empty($this->params['breadcrumbs'])): ?>
    <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
<?php endif ?>
<?= $content ?>

<footer id="footer" class="mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-md-12 text-center">
                Размещенная на настоящем сайте информация носит исключительно информационный характер и ни при каких условиях не является публичной офертой, определяемой положениями ч. 2 ст. 437 Гражданского кодекса Российской Федерации.
                <br/>
                <a class="ShopFooter-module__link" href="/site/agreement" target="_blank" rel="noreferrer">Пользовательское соглашение</a>
                <a class="ShopFooter-module__link" href="/site/privacy" target="_blank" rel="noreferrer">Политика конфиденциальности</a>
                <a class="ShopFooter-module__link" href="mailto:help@prostoj.store" target="_blank" rel="noreferrer">prostoj.store</a>
            </div>
        </div>
    </div>
</footer>


<div class="modal modal-alert fade" id="modal-dialog" tabindex="-1" aria-labelledby="modal-dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
<!--            <div class="modal-footer">-->
<!--                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>-->
<!--                <button type="button" class="btn btn-primary">Save changes</button>-->
<!--            </div>-->
        </div>
    </div>
</div>
<!-- Yandex.Metrika counter -->
<script type="text/javascript" >
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(97456083, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
    });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/97456083" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

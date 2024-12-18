<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\assets\SocketAsset;
use frontend\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use common\components\web\LanguagePicker;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use common\models\user\UserBalance;

SocketAsset::register($this);
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
        'label'   => '<i class="fab fa-steam"></i> '.Yii::t('common', 'Войти через Steam'),
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
        'label'   => '<i class="fab fa-steam"></i> ' . Yii::t('common', 'Войти через Steam'),
        'url'     => '/auth/oauth?authclient=steam',
        'encode' => false,
        'visible' => Yii::$app->user->isGuest,
    ]
];
$baseUrl = Yii::$app->params['domain'];
$ws = Yii::$app->params['ws'];
$this->registerJs(<<<JS
    var baseUrl = '{$baseUrl}';
    var ws = '{$ws}';
JS
    , \yii\web\View::POS_BEGIN);
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
                'label'   => '<i class="fas fa-shopping-basket"></i> ' . Yii::t('common', 'Профиль'),
                'encode' => false,
                'url'     => '/user/tasks',
            ],
            [
                'label'   => '<i class="fas fa-wallet"></i> ' . Yii::t('common', 'Пополнить баланс'),
                'encode' => false,
                'linkOptions' => [
                        'class' => 'show-modal-link',
                        'data-title' => Yii::t('common', 'Пополнить баланс'),
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
            'data-title' => Yii::t('common', 'Пополнить баланс'),
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
    'url'     => '/stats?server=' . Yii::$app->params['statisticsServerDefault'],
];
if (Yii::$app->params['skindrops']) {
    $mobileMenu[] = [
        'label'   => '<i class="fas fa-exchange-alt"></i> ' . Yii::t('common', 'Как получать скины?'),
        'encode' => false,
        'url'     => '/skindrops',
    ];
}
if (Yii::$app->params['basketSite']) {
    $mobileMenu[] = [
        'label'   => '<i class="fa-solid fa-basket-shopping"></i> ' . Yii::t('common', 'Вывод предметов'),
        'encode' => false,
        'url'     => '/store',
        'linkOptions' => [
            'target' => '_blank',
        ],
    ];
}
$mobileMenu[] = [
    'label'   => '<i class="fab fa-discord"></i> ' . Yii::t('common', 'Мы в Discord'),
    'encode' => false,
    'url'     => Yii::$app->params['discord'],
    'linkOptions'     => ['target' => '_blank'],
    'visible' => !empty(Yii::$app->params['discord']),
];
$mobileMenu[] = [
    'label'   => '<i class="fab fa-vk"></i> ' . Yii::t('common', 'Мы в Вконтакте'),
    'encode' => false,
    'url'     => Yii::$app->params['vk'],
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
<html lang="<?= substr(Yii::$app->language, 0, 2) ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <?php $this->head() ?>
    <link href="<?=Yii::$app->params['css']?>" rel="stylesheet">
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>
<script>
<?php if (Yii::$app->user->isGuest):?>
    var steam_id = undefined;
    var token = undefined;
<?php else: ?>
    var steam_id = "<?=Yii::$app->user->identity->steam_id?>";
    var token = "<?=Yii::$app->user->identity->getJwtToken()?>";
<?php endif; ?>
</script>

<header id="header">
    <nav class="navbar-expand-md navbar-dark bg-dark navbar">
        <div class="container-fluid">
            <div class="header_mobile">
                <a class="navbar-brand" href="<?=Yii::$app->homeUrl?>">
                    <img src="<?=Yii::$app->params['logo']?>"/>
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
                        <?=Html::img(Yii::$app->user->identity->getAvatar(), ['width' => '40px'])?>
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
                    <img src="<?=Yii::$app->params['logo']?>"/>
                </a>
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
                            'label'   => '<i class="fa-solid fa-chart-pie"></i> ' . Yii::t('common', 'Статистика'),
                            'url'     => '/stats?server=' . Yii::$app->params['statisticsServerDefault'],
                            'encode' => false,
                            'options'     => [
                                'class' => 'menu-stats'
                            ],
                        ],
                        [
                            'label'   => '<i class="fa-solid fa-exchange-alt"></i> ' . Yii::t('common', 'Как получать скины?'),
                            'url'     => '/skindrops',
                            'encode' => false,
                            'visible' => Yii::$app->params['skindrops'],
                            'options'     => [
                                'class' => 'menu-skindrops'
                            ],
                        ],
                        [
                            'label'   => '<i class="fa-solid fa-basket-shopping"></i> ' . Yii::t('common', 'Вывод предметов'),
                            'url'     => '/store',
                            'visible' => Yii::$app->params['basketSite'],
                            'encode' => false,
                            'options'     => [
                                'class' => 'menu-skindrops',
                            ],
                            'linkOptions' => [
                                'target' => '_blank',
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
                            'options' => ['class' =>'vk_social', 'title' => Yii::t('common', 'Мы в Вконтакте')],
                            'url'     => Yii::$app->params['vk'],
                            'linkOptions'     => ['target' => '_blank'],
                        ],
                        [
                            'label'   => '<i class="fab fa-discord"></i>',
                            'encode' => false,
                            'options' => ['class' =>'discord_social', 'title' => Yii::t('common', 'Мы в Discord')],
                            'url'     => Yii::$app->params['discord'],
                            'linkOptions'     => ['target' => '_blank'],
                            'visible' => !empty(Yii::$app->params['discord']),
                        ],
                    ],
                    'options' => ['class' =>'navbar-nav nav-pills header-social-menu'],
                ]);
                ?>
                <div class="header-language-picker">
                    <?php echo LanguagePicker::widget([
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

<?php if (isset($this->params['breadcrumbs'])): ?>
    <div class="breadcrumbs_wrap">
        <div class="container-fluid">
            <?= \yii\bootstrap5\Breadcrumbs::widget(
                [
                    'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                ]
            ) ?>
        </div>
    </div>
<?php endif; ?>
<?= $content ?>

<footer id="footer" class="mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-md-12 text-center">
                <?=Yii::t('common', 'Размещенная на настоящем сайте информация носит исключительно информационный характер и ни при каких условиях не является публичной офертой, определяемой положениями ч. 2 ст. 437 Гражданского кодекса Российской Федерации.')?>
                <div class="footer_links">
                    <a class="ShopFooter-module__link" href="/site/agreement" target="_blank" rel="noreferrer"><?=Yii::t('common', 'Пользовательское соглашение')?></a>
                    <a class="ShopFooter-module__link" href="/site/privacy" target="_blank" rel="noreferrer"><?=Yii::t('common', 'Политика конфиденциальности')?></a>
                    <a class="ShopFooter-module__link" href="/site/personalinformation" target="_blank" rel="noreferrer"><?=Yii::t('common', 'Обработка персональных данных')?></a>
                    <a class="ShopFooter-module__link" href="mailto:<?=Yii::$app->params['email']?>" target="_blank" rel="noreferrer"><?=Yii::$app->params['email']?></a>
                </div>
            </div>
        </div>
    </div>
</footer>
<?php //echo $this->render('@frontend/views/widgets/_support'); ?>

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
<?php if (YII_ENV_PROD && Yii::$app->params['metrika']): ?>
<?php if (Yii::$app->language == 'ru-RU'): ?>
<!-- Yandex.Metrika counter -->
<script type="text/javascript" >
    (function (d, w, c) {
        (w[c] = w[c] || []).push(function() {
            try {
                w.yaCounter97456083 = new Ya.Metrika({
                    id:97456083,
                    clickmap:true,
                    trackLinks:true,
                    accurateTrackBounce:true,
                    webvisor:true
                });
            } catch(e) { }
        });

        var n = d.getElementsByTagName("script")[0],
            x = "https://mc.yandex.ru/metrika/watch.js",
            s = d.createElement("script"),
            f = function () { n.parentNode.insertBefore(s, n); };
        for (var i = 0; i < document.scripts.length; i++) {
            if (document.scripts[i].src === x) { return; }
        }
        s.type = "text/javascript";
        s.async = true;
        s.src = x;

        if (w.opera == "[object Opera]") {
            d.addEventListener("DOMContentLoaded", f, false);
        } else { f(); }
    })(document, window, "yandex_metrika_callbacks");
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/97456083" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
<?php else: ?>
        <!-- Yandex.Metrika counter -->
        <script type="text/javascript" >
            (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();
                for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
            (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

            ym(98935165, "init", {
                clickmap:true,
                trackLinks:true,
                accurateTrackBounce:true
            });
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/98935165" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
        <!-- /Yandex.Metrika counter -->
<?php endif; ?>
<?php endif; ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

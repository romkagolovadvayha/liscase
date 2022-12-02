<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

AppAsset::register($this);
\frontend\assets\OnlineCounterAsset::register($this);
\frontend\assets\BalanceAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => '@web/favicon.ico']);

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
if (!Yii::$app->user->isGuest) {
    $balanceStr = Yii::$app->user->identity->getPersonalBalance()->getBalanceFormat();
    $balance = Yii::$app->user->identity->getPersonalBalance()->balance;
    $this->registerJs(<<<JS
    var balanceStr = '{$balanceStr}';
    var balance = {$balance};
JS
        , \yii\web\View::POS_BEGIN);
    $rightMenu[] = [
        'label'   => '<div class="balance-item">
                                    <div class="name">' . Yii::$app->user->identity->userProfile->name . '</div>
                                    <div class="balance"><span class="balance_count">' . $balanceStr . '</span> ₽</div>
                            </div>',
        'visible' => !Yii::$app->user->isGuest,
        'url'     => '/user/payment',
        'encode' => false,
    ];
    $rightMenu[] = [
        'label'   => Html::img(Yii::$app->user->identity->userProfile->avatar, ['width' => '32px']),
        'visible' => !Yii::$app->user->isGuest,
        'encode' => false,
        'items' => [
            [
                'label'   => 'Профиль',
                'url'     => '/user/profile',
            ],
            [
                'label'   => 'Инвентарь',
                'url'     => '/user/inventory',
            ],
            [
                'label'   => 'АДМИНКА',
                'url'     => Yii::$app->params['backendUrl'],
                'visible' => Yii::$app->user->identity && Yii::$app->user->identity->isAccessBackend(),
            ],
            [
                'label'  => Yii::t('common', 'Выйти'),
                'url'    => '/site/logout',
            ],
        ],
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

<header id="header">
    <nav class="navbar-expand-md navbar-dark bg-dark navbar">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarTogglerDemo01">
                <a class="navbar-brand" href="<?=Yii::$app->homeUrl?>"><?=Yii::$app->name?></a>
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
                            'url'     => '#',
                        ],
                        [
                            'label'   => 'FAQ ' . Html::img('/images/icons/faq.svg', ['width' => '18px']),
                            'url'     => '/faq',
                            'encode' => false,
                            'options'     => [
                                'class' => 'menu-faq'
                            ],
                        ],
                    ],
                    'options' => ['class' =>'navbar-nav me-auto mb-2 mb-lg-0 header-left-menu'],
                ]);
                ?>
                <?=Nav::widget([
                    'items' => [
                        [
                            'label'   => Html::img('/images/social/vk.svg', ['width' => '22px']),
                            'encode' => false,
                            'url'     => 'https://vk.com/ezdrop_pro',
                            'linkOptions'     => ['target' => '_blank'],
                        ],
                        [
                            'label'   => Html::img('/images/social/telegram.svg', ['width' => '22px']),
                            'encode' => false,
                            'url'     => 'https://t.me/ezdrop_pro',
                            'linkOptions'     => ['target' => '_blank'],
                        ],
                    ],
                    'options' => ['class' =>'navbar-nav nav-pills header-social-menu'],
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
        <div class="row text-muted">
            <div class="col-md-6 text-center text-md-start">&copy; <?=Yii::$app->name?> <?= date('Y') ?></div>
            <div class="col-md-6 text-center text-md-end"></div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

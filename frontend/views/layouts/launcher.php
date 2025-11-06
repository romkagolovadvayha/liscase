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

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => '/images/favicon.svg']);

$baseUrl = Yii::$app->settings->get('site_domain');
$ws = Yii::$app->params['ws'];
$this->registerJs(<<<JS
    var baseUrl = '{$baseUrl}';
    var ws = '{$ws}';
JS
    , \yii\web\View::POS_BEGIN);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= substr(Yii::$app->language, 0, 2) ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --logo: url(/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg);
            --favicon: url(/uploads/site/design/273ce7b2b2b39b5df9a65d75d0b2b49a.svg);
            --statsBlockImage: url(/uploads/site/design/6005c9eba26ecf8aeaf5e12244103402.png);
            --bonusBlockImage: url(/uploads/site/design/28d1012d81804e7cc4e1e29378023522.png);
            --promoPopupImage: url(/uploads/site/design/ed51829cce613849269a12c3e117e1bf.png);
            --payPopupImage: url(/uploads/site/design/3cb8da4cc81f5f0836121ad696e51911.png);
            --wipeBlockPopupImage: url(/uploads/site/design/a9c5acfa1cbb82d174f22696a9086f1c.png);
            --primary-colors-main: #eb0c35;
            --primary-colors-secondary: #ff6134;
            --primary-colors-secondary-opacity: rgba(255, 97, 52, 0.15);
            --primary-colors-white: #ffffff;
            --primary-colors-main-opacity: rgba(235, 12, 53, 0.4);
            --background-main: #080224;
            --background-secondary: #19102d;
            --background-teritiary: #2e1a3b;
            --text-main: #ece4f3;
            --text-secondary: #8f8f8f;
            --text-teritiary: #b5b5b5;
            --text-disabled: rgba(236, 228, 243, 0.2);
            --icon-main: #564a66;
            --icon-hover: #7f718c;
            --icon-mini: #ff6134;
            --icon-in-button: #ece4f3;
            --status-text-color: #ece4f3;
            --status-background-opacity: rgba(236, 228, 243, 0.15);
            --tooltip-background: #272140;
            --tooltip-text-color: #ece4f3;
            --badge-text-color: #ece4f3;
            --badge-background: #eb0c35;
            --border-color-default: #3e3249;
            --border-color-hover: #7f718c;
            --border-color-active: #ece4f3;
            --link-color-default: #ff4814;
            --link-color-hover: #ff7834;
            --link-color-disabled: #67504a;
            --button-disabled: rgba(255, 255, 255, 0.2);
            --button-main-new-price-color-default: #ece4f3;
            --button-main-old-price-color-default: rgba(236, 228, 243, 0.8);
            --button-secondary-text-color-default: #ece4f3;
            --button-secondary-border-color-default: #3e3249;
            --button-teritiary-new-price-color-default: #ece4f3;
            --button-teritiary-old-price-color-hover: rgba(236, 228, 243, 0.8);
            --button-teritiary-background: #2e1a3b;
            --system-colors-success-color: #009136;
            --progress-step-color: #ff7d58;
            --system-colors-gold: #f8b34d;
            --system-colors-silver: #b4b4b4;
            --system-colors-bronze: #af7355;
            --online: #4bcc18;
            --status-radius: 20px;
            --button-radius: 6px;
            --button-main-radius: 6px;
            --button-secondary-radius: 6px;
            --button-teritiary-radius: 6px;
            --shadow-card: 4px 4px 4px 0px rgba(0, 0, 0, 0.25);
            --base-linear-gradiend: linear-gradient(90deg, var(--primary-colors-main) 50%, var(--primary-colors-secondary) 100%);
            --block-radius: 16px;
            --card-radius: 8px;
            --server-command: #aaf16e;
            --server-command-primary: #feeda1;
            --light-the-best-background: url(/uploads/site/colors/0a7b25a64742af33841f6b08ab3d7820.svg);
            --light-background: url(/uploads/site/colors/811d0f50009f072bf3c00ab56fa6aaf4.svg);
            --color-rules-punishment: #eb0c35;
            --color-rules-icon: #aaf16e;
            --avatar-radius: 6px;
            --icon-money: url(/uploads/site/design/72d342d54b58fdf14adc5bd6ea00b994.svg);
            --image-not-auth: url(/uploads/site/design/35ffaaecf35b4348b2438718ebfe5d37.png);
            --iconskins: url(/uploads/site/design/5fbb804ed4015b8283707b0e080ed839.svg);
            --servers-image: url(/uploads/site/design/8aa94e4f99ab7e3abb1972ce8150fd20.png);
            --button-primary-image-hover: linear-gradient(90deg, var(--primary-colors-main) 0%, var(--primary-colors-secondary) 50.04%, #ffb834 100%);
            --opacity-stat-background: #2e1a3b5c;
            --categories-image-shadow: url(/uploads/site/colors/7864300184aa67de3a3c193391c9cbf0.svg);
            --categories-image-glow: url(/uploads/site/colors/2b08faaace1d26d11f676cfb9523cb40.svg);
            --categories-is-image: 1;
            --indicator-online: url(/uploads/site/colors/29e31f1a394723cc94f22128cd0f3ea4.svg);
            --font-link: https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap;
            --font-main: Roboto, sans-serif;
            --watemark: url(/uploads/site/design/d83ad05567daae70fe32228c441ace9c.png);
            --category-card-image-size: 150px;
            --category-cards-grid: repeat(4, 1fr);
            --avatar-default: url(/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png);
            --icon-social-main: #a298ae;
            --bonusBlockImageVideo: /uploads/site/design/ff7ade07c1cec75ebcc576f7c00696c9.webm;
            --statsBlockImageVideo: /uploads/site/design/52c193cc8fc981b8803c1d417c3491a3.webm;
            --background-secondary-dark: #100b13;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            min-height: 100%;
            font-family: var(--font-main);
            background: var(--background-main);
            color: var(--text-main);
            overflow-x: hidden;
        }
        
        body {
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(235, 12, 53, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 97, 52, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        #content {
            position: relative;
            z-index: 1;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--background-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border-color-default);
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--border-color-hover);
        }
        
        /* Selection styling */
        ::selection {
            background: var(--primary-colors-main);
            color: var(--text-main);
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
    </style>
    <?php $this->head() ?>
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

<?= $content ?>
<?=Yii::$app->settings->get('metrics_code'); ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

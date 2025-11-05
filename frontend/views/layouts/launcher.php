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
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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

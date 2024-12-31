<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\assets\SocketAsset;
use yii\bootstrap5\Html;
use common\models\user\UserBalance;
use frontend\assets\MainAsset;

SocketAsset::register($this);
AppAsset::register($this);
MainAsset::register($this);
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

$baseUrl = Yii::$app->params['domain'];
$ws = Yii::$app->params['ws'];
$this->registerJs(<<<JS
    var baseUrl = '{$baseUrl}';
    var ws = '{$ws}';
JS
    , \yii\web\View::POS_BEGIN);
$balance = 0;
if (!Yii::$app->user->isGuest) {
    $pBalance = Yii::$app->user->identity->getPersonalBalance();
    $balanceStr = $pBalance->getBalanceFormat();
    $balance    = $pBalance->balanceCeil;
    $this->registerJs(
        <<<JS
    var balanceStr = '{$balanceStr}';
    var balance = {$balance};
JS,
        \yii\web\View::POS_BEGIN
    );
}
$userData = [];
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
    $userData = [
        'username' => $user->username,
        'steam_id' => $user->steam_id,
        'avatar' => $user->getAvatar(),
        'currency' => UserBalance::getCurrency(),
        'balance' => $balance,
        'server' => $user->server,
    ];
}
?>
<?php $this->beginPage() ?>
<?=Yii::$app->view->render('main.twig', [
    'title' => Html::encode($this->title),
    'cssLink' => Yii::$app->params['css'],
    'head' => '<![CDATA[YII-BLOCK-HEAD]]>',
    'lang' => substr(Yii::$app->language, 0, 2) ,
    'body' => Yii::$app->view->render('body', [
        'content' => $content,
        'userData' => $userData,
    ]),
]);?>
<?php $this->endPage() ?>

<?php

/** @var yii\web\View $this */
/** @var string $content */

use frontend\assets\AppAsset;
use frontend\assets\SocketAsset;
use yii\bootstrap5\Html;
use common\models\user\UserBalance;
use frontend\assets\MainAsset;
use common\models\servers\Servers;

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
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::$app->settings->get('design_favicon')]);

$baseUrl = Yii::$app->settings->get('site_domain');
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
    var chatId = undefined;
JS,
        \yii\web\View::POS_BEGIN
    );
}
$userData = [];
$servers = Servers::find()
                  ->cache(30)
                  ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                  ->orderBy(['sort' => SORT_ASC])
                  ->all();
$userData['SERVER_ACTIVE_ID'] = $servers[0]->id;
$userData['SERVER_ACTIVE_TAG'] = $servers[0]->tag;
$supportCount = 0;
$notifications = [];
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
    $userData = [
        'username' => $user->username,
        'steam_id' => $user->steam_id,
        'avatar' => $user->getAvatar(),
        'currency' => UserBalance::getCurrency(),
        'balance' => $balance,
        'server' => $user->server,
        'blocked' => $user->status === \common\models\user\User::STATUS_BLOCKED,
    ];
    if (!empty($userData['server'])) {
        foreach ($servers as $server) {
            if ($server->id == $userData['server']->id) {
                $userData['SERVER_ACTIVE_ID'] = $userData['server']->id;
                $userData['SERVER_ACTIVE_TAG'] = $userData['server']->tag;
                break;
            }
        }
    }
    $notifications = $user->notifications();
}

$settings = Yii::$app->settings;
$lang = substr(Yii::$app->language, 0, 2);

$H1 = Html::encode(!empty($this->params['h1']) ? $this->params['h1'] : null);
if (Yii::$app->user->isGuest || !$userData['blocked']) {
    $body = Yii::$app->view->render('body', [
        'content' => $content,
        'userData' => $userData,
        'lang' => $lang,
        'H1' => $H1,
        'servers' => $servers,
        'notifications' => $notifications,
        'SETTINGS' => $settings,
    ]);
} else {
    $body = Yii::$app->view->render('body_blocked', [
        'content' => $content,
        'userData' => $userData,
        'lang' => $lang,
        'H1' => $H1,
        'servers' => $servers,
        'SETTINGS' => $settings,
    ]);
}

?>
<?php $this->beginPage() ?>
<?=Yii::$app->view->render('main.twig', [
    'title' => Html::encode($this->title),
    'H1' => $H1,
    'SETTINGS' => $settings,
    'cssLink' => Yii::$app->params['css'],
    'head' => '<![CDATA[YII-BLOCK-HEAD]]>',
    'lang' => $lang,
    'body' => $body,
]);?>
<?php $this->endPage() ?>

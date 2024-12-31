<?php
/** @var string $content */
/** @var array $userData */

use common\models\servers\Servers;
use common\components\web\Cookie;

$servers = Servers::find()
                  ->cache(30)
                  ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                  ->orderBy(['sort' => SORT_ASC])
                  ->all();

$breadcrumbs = null;
if (isset($this->params['breadcrumbs'])) {
    $breadcrumbs = \yii\bootstrap5\Breadcrumbs::widget(['links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],]);
}
$projectStats = \common\models\statistics\Statistics::projectStats();
$activeServerId = $servers[0]->id;
if (!empty($userData['server'])) {
    foreach ($servers as $server) {
        if ($server->id == $userData['server']->id) {
            $activeServerId = $userData['server']->id;
            break;
        }
    }
}
$userData['SERVER_ACTIVE_ID'] = $activeServerId;
$hiddenMenu = Cookie::getValue('isMenuHide') == 'true';
//$parser = new \ScssPhp\ScssPhp\Compiler();
//$compileFile = $parser->compileFile(__DIR__ . '/styles.scss');
//file_put_contents(Yii::getAlias('@frontend/web/css/styles.min.css'), $compileFile->getCss());
?>

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
<?=Yii::$app->view->render('body.twig', [
    'content' => $content,
    'this' => $this,
    'breadcrumbs' => $breadcrumbs,
    'email' => Yii::$app->params['email'],
    'user' => $userData,
    'MENU_HIDDEN' => $hiddenMenu,
//    'ALERT_MESSAGE' => $this->render('@frontend/views/widgets/_alert'),
//    'SKINDROPS_BLOCK' => $this->render('@frontend/views/widgets/_skindrops'),
    'SERVERS_BLOCK' => $this->render('@frontend/views/widgets/_servers', ['servers' => $servers, 'PROJECT_STATS' => $projectStats]),
//    'PROMOCODE_FORM' => $this->render('@frontend/views/layouts/_promocode_line'),
    'TOP_BLOCK' => $this->render('@frontend/views/widgets/_top', ['servers' => $servers, 'PROJECT_STATS' => $projectStats, 'userData' => $userData]),
    'LIVE_BLOCK' => $this->render('@frontend/views/widgets/_live', ['servers' => $servers, 'PROJECT_STATS' => $projectStats, 'userData' => $userData]),
    'HEADER' => Yii::$app->view->render('header.twig', [
        'HOME_URL' => Yii::$app->homeUrl,
        'LOGO_IMAGE' => Yii::$app->params['logo'],
        'USER_GUEST' => Yii::$app->user->isGuest,
        'user' => $userData,
    ]),
    'MENU' => Yii::$app->view->render('menu.twig', [
        'MENU_HIDDEN' => $hiddenMenu
    ]),
    'MODAL' => Yii::$app->view->render('modal.twig', [

    ]),
    'FOOTER' => Yii::$app->view->render('footer.twig', [
        'EMAIL' => Yii::$app->params['email'],
        'LOGO_IMAGE' => Yii::$app->params['logo'],
        'HOME_URL' => Yii::$app->homeUrl,
        'DOMAIN' => Yii::$app->params['domain'],
    ])
]);?>

<?=Yii::$app->view->render('metrics.twig')?>
<?php $this->endBody() ?>
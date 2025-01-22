<?php
/** @var string $content */
/** @var string $lang */
/** @var array $userData */
/** @var Servers[] $servers */
/** @var array $notifications */

use common\models\servers\Servers;
use common\components\web\Cookie;
use common\models\statistics\Teams;
use common\models\statistics\Statistics;
use common\models\building\Building;
use common\models\user\User;

$breadcrumbs = null;
$page = isset($this->params['page']) ? $this->params['page'] : '';
if (isset($this->params['breadcrumbs'])) {
    $breadcrumbs = \yii\bootstrap5\Breadcrumbs::widget(['links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],]);
}
$projectStats = Statistics::projectStats();

$lang = substr(Yii::$app->language, 0, 2);
$hiddenMenu = Cookie::getValue('isMenuHide') == 'true';
//$parser = new \ScssPhp\ScssPhp\Compiler();
//$compileFile = $parser->compileFile(__DIR__ . '/styles.scss');
//file_put_contents(Yii::getAlias('@frontend/web/css/styles.min.css'), $compileFile->getCss());
$buildingsBlock = null;
$teamBlock = null;
if (!empty($this->params['_user'])) {
    /** @var User $_user */
    $_user = $this->params['_user'];
    $_server = $this->params['_server'];
    $buildings = Building::getBuildings($_user);
    if (!empty($buildings)) {
        $buildingsBlock = $this->render('@frontend/views/widgets/buildings.twig', ['ITEMS' => $buildings]);
    }
    $team = Teams::getTeam($_server, $_user->steam_id);
    $teamBlock = $this->render('@frontend/views/widgets/teams.twig', ['ITEMS' => $team]);
}
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
        var lang = "<?=$lang?>";
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
    'BUILDINGS_BLOCK' => $buildingsBlock,
    'TEAMS_BLOCK' => $teamBlock,
    'HEADER' => Yii::$app->view->render('header.twig', [
        'HOME_URL' => Yii::$app->homeUrl,
        'LOGO_IMAGE' => Yii::$app->params['logo'],
        'USER_GUEST' => Yii::$app->user->isGuest,
        'user' => $userData,
        'lang' => $lang,
        'DOMAIN' => Yii::$app->params['domain'],
    ]),
    'MENU' => Yii::$app->view->render('menu.twig', [
        'MENU_HIDDEN' => $hiddenMenu,
        'USER' => $userData,
        'PAGE' => $page,
        'NOTIFICATIONS' => $notifications,
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
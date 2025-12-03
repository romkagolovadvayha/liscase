<?php
/** @var string $content */
/** @var string $lang */
/** @var array $userData */
/** @var Servers[] $servers */
/** @var array $notifications */
/** @var $SETTINGS */
/** @var $H1 */

use common\models\servers\Servers;
use common\components\web\Cookie;
use common\models\statistics\Teams;
use common\models\statistics\Statistics;
use common\models\building\Building;
use common\models\user\User;
use common\models\statistics\Kills;

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
$killsBlock = null;
$profileBlock = null;
$blogListCategoryBlock = null;
$blogCommentsBlock = null;
$blogSimilarBlock = null;
if (!empty($this->params['_blog_category_block'])) {
    $paramsBlock = [];
    if (!empty($this->params['_blog_category'])) {
        $paramsBlock['category'] = $this->params['_blog_category'];
    }
    $blogListCategoryBlock = $this->render('../layouts/_side_category_list', $paramsBlock);
}
if (!empty($this->params['_blog_comments_block'])) {
    $blogCommentsBlock = $this->render('../layouts/_side_comments_list');
}
//if (!empty($this->params['_blog_similar_block'])) {
//    $blogSimilarBlock = $this->render('../layouts/_side_similar_posts', ['model' => $this->params['_blog_model']]);
//}
$user = Yii::$app->user->identity;
$serverInfoBlock = null;
if (!empty($user) && !empty($user->server) && $user->last_visit_server_at > date('Y-m-d H:i:s', time() - 5 * 60)) {
    $serverInfoBlock = $this->render('@frontend/views/widgets/server_info.twig', [
                                              'SERVER' => $user->server,
                                              'USER' => $user,
                                              'PROJECT_STATS' => $projectStats,
                                              'SETTINGS' => $SETTINGS,
                                              'PAGE' => $page
                                         ]);
}
if (!empty($this->params['_user'])) {
    /** @var User $_user */
    $_user = $this->params['_user'];
    $_server = $this->params['_server'];
    $buildings = Building::getBuildings($_user);
    if (!empty($buildings)) {
        $buildingsBlock = $this->render('@frontend/views/widgets/buildings.twig', ['ITEMS' => $buildings]);
    }
    
    // Проверяем, является ли текущий пользователь владельцем страницы
    $isOwner = !Yii::$app->user->isGuest && Yii::$app->user->id === $_user->id;
    
    // Проверяем, есть ли команда у пользователя (даже если она скрыта)
    $hasTeam = false;
    try {
        $teamRows = \common\models\teams\Teams::find()->alias('t')
            ->innerJoin(\common\models\teams\Teams::tableName() . ' t2',
                't.leader_user_id = t2.leader_user_id AND t.server_id = t2.server_id AND t.wipe = t2.wipe'
            )
            ->where([
                't2.user_id' => $_user->user_id,
                't2.server_id' => $_server->id,
                't2.wipe' => $_server->currentWipe(),
            ])
            ->exists();
        $hasTeam = $teamRows;
    } catch (\Exception $e) {
        // Игнорируем ошибку при проверке
    }
    
    // Показываем блок команды только если:
    // 1. У пользователя не скрыт список команды, ИЛИ
    // 2. Текущий пользователь является владельцем страницы
    if (!$isOwner && $_user->hasHideTeam()) {
        // Если команда есть, но скрыта, показываем сообщение
        if ($hasTeam) {
            $teamBlock = $this->render('@frontend/views/widgets/team_hidden.twig');
        } else {
            // Если команды нет, не показываем ничего
            $teamBlock = null;
        }
    } else {
        try {
            $team = \common\models\teams\Teams::getTeamList($_server->id, $_user->user_id, $_server->currentWipe());
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("getTeamList: " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage());
            $team = [];
        }
        $teamBlock = $this->render('@frontend/views/widgets/teams.twig', ['ITEMS' => $team]);
    }
    
    $kills = Kills::getKillsLive($_server, $_user);
    $killsBlock = $this->render('@frontend/views/widgets/kills.twig', ['ITEMS' => $kills]);
}
if (!empty($this->params['_profile'])) {
    /** @var User $_user */
    $profileBlock = $this->render('@frontend/views/widgets/profile.twig', ['userData' => $userData, 'PAGE' => $page, 'SETTINGS' => $SETTINGS]);
}
$topBlock = null;
$liveBlock = null;
if (empty($this->params['_blog_similar_block'])) {
    $topBlock = $this->render('@frontend/views/widgets/_top', ['servers' => $servers, 'PROJECT_STATS' => $projectStats, 'userData' => $userData, 'SETTINGS' => $SETTINGS, 'PAGE' => $page]);
    $liveBlock = $this->render('@frontend/views/widgets/_live', ['servers' => $servers, 'PROJECT_STATS' => $projectStats, 'userData' => $userData, 'SETTINGS' => $SETTINGS, 'PAGE' => $page]);
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
    'H1' => $H1,
    'email' => Yii::$app->params['email'],
    'user' => $userData,
    'MENU_HIDDEN' => $hiddenMenu,
    'SERVER_INFO_BLOCK' => $serverInfoBlock,
    'PROFILE_BLOCK' => $profileBlock,
//    'ALERT_MESSAGE' => $this->render('@frontend/views/widgets/_alert'),
//    'SKINDROPS_BLOCK' => $this->render('@frontend/views/widgets/_skindrops'),
    'SERVERS_BLOCK' => $this->render('@frontend/views/widgets/_servers', ['servers' => $servers, 'PROJECT_STATS' => $projectStats, 'SETTINGS' => $SETTINGS, 'PAGE' => $page]),
//    'PROMOCODE_FORM' => $this->render('@frontend/views/layouts/_promocode_line'),
    'TOP_BLOCK' => $topBlock,
    'LIVE_BLOCK' => $liveBlock,
    'BUILDINGS_BLOCK' => $buildingsBlock,
    'KILLS_BLOCK' => $killsBlock,
    'TEAMS_BLOCK' => $teamBlock,
    'BLOG_CATEGORY_LIST_BLOCK' => $blogListCategoryBlock,
    'BLOG_COMMENTS_LIST_BLOCK' => $blogCommentsBlock,
//    'BLOG_SIMILAR_LIST_BLOCK' => $blogSimilarBlock,
    'HEADER' => Yii::$app->view->render('header.twig', [
        'HOME_URL' => Yii::$app->homeUrl,
        'LOGO_IMAGE' => Yii::$app->settings->get('design_logo'),
        'USER_GUEST' => Yii::$app->user->isGuest,
        'user' => $userData,
        'lang' => $lang,
        'DOMAIN' => Yii::$app->settings->get('site_domain'),
        'SETTINGS' => $SETTINGS,
        'PAGE' => $page,
    ]),
    'MENU' => Yii::$app->view->render('menu.twig', [
        'MENU_HIDDEN' => $hiddenMenu,
        'USER' => $userData,
        'NOTIFICATIONS' => $notifications,
        'PAGE' => $page,
        'SETTINGS' => $SETTINGS,
        'USER_GUEST' => Yii::$app->user->isGuest,
    ]),
    'MODAL' => Yii::$app->view->render('modal.twig', [
        'SETTINGS' => $SETTINGS,
    ]),
    'FOOTER' => Yii::$app->view->render('footer.twig', [
        'EMAIL' => Yii::$app->params['email'],
        'LOGO_IMAGE' => Yii::$app->settings->get('design_logo'),
        'SETTINGS' => $SETTINGS,
        'HOME_URL' => Yii::$app->homeUrl,
        'DOMAIN' => Yii::$app->settings->get('site_domain'),
        'PAGE' => $page,
        'USER_GUEST' => Yii::$app->user->isGuest,
    ]),
    'SETTINGS' => $SETTINGS,
    'PAGE' => $page
]);?>

<?=Yii::$app->view->render('metrics.twig')?>
<?php $this->endBody() ?>
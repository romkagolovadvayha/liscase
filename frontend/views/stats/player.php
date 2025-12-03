<?php

/** @var yii\web\View $this */
/** @var Servers $server */
/** @var Servers[] $servers */
/** @var string $steamId */
/** @var User $user */

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use yii\web\NotFoundHttpException;
use common\models\user\User;
use frontend\widgets\Alert;
use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use common\models\statistics\Teams;

$this->title = $user->username . " " . Yii::t('common', 'статистика на сервере') . " " . Yii::t('database', $server->name);
$this->params['page'] = 'stats';

$this->registerMetaTag(['property' => 'robots', 'content' => 'noindex, follow'], 'robots');

$IS_GUEST = Yii::$app->user->isGuest;
$wipe = $server->currentWipe();
$player = Statistics::getPlayerStats($server, $user->steam_id, $wipe);
$images = Statistics::productsImages();
$names = Statistics::productsNames();

$displayStatus = $user->getDisplayStatus();
$isHidden = $displayStatus === null;
if ($isHidden) {
    $statusClass = ' profile_hidden';
} elseif ($displayStatus) {
    $statusClass = '';
} else {
    $statusClass = ' profile_offline';
}
if ($user->status === User::STATUS_BLOCKED) {
    $statusClass = ' profile_banned';
}
$wipes = Statistics::find()
                   ->select('COUNT(DISTINCT `wipe`)')
                   ->andWhere(['steam_id' => $user->steam_id])
                   ->scalar() ?? 0;
// Получаем все активные задания из tasks_v2
$tasksV2 = \common\models\tasks_v2\TaskV2::find()
    ->where(['is_active' => 1])
    ->orderBy(['sort' => SORT_ASC])
    ->all();

// Получаем выполненные задания пользователя
$userCompletions = \common\models\tasks_v2\TaskV2UserCompletion::find()
    ->where(['user_id' => $user->id])
    ->indexBy('task_id')
    ->all();

// Формируем массив заданий с информацией о выполнении
$awards = [];
foreach ($tasksV2 as $task) {
    $completed = isset($userCompletions[$task->id]) && $userCompletions[$task->id]->count_completed > 0;
    
    // Получаем изображение задания
    if (!empty($task->image_path)) {
        // Если путь не начинается с /, добавляем его
        $image = strpos($task->image_path, '/') === 0 ? $task->image_path : '/' . $task->image_path;
    } else {
        $image = '/images/awards/default.png';
    }
    
    $awards[] = [
        'id' => $task->id,
        'name' => $task->title,
        'image' => $image,
        'completed' => $completed,
    ];
}

// Сортируем: сначала выполненные
usort($awards, function($a, $b) {
    if ($a['completed'] === $b['completed']) {
        return 0;
    }
    return $a['completed'] ? -1 : 1;
});

// Подсчитываем статистику
$totalTasks = count($awards);
$completedTasks = 0;
foreach ($awards as $award) {
    if ($award['completed']) {
        $completedTasks++;
    }
}

$awardsStats = [
    'completed' => $completedTasks,
    'total' => $totalTasks,
];

$kdr = Statistics::getParam($player, 'deaths') > 0 ? round(Statistics::getParam($player, 'kills') / Statistics::getParam($player, 'deaths'), 2) : Statistics::getParam($player, 'kills');
?>


<!--<div class="flex items-center justify-space-between gap-x-12 mb-28">-->
<!--    <input placeholder="Введите ник или Steam ID" type="text" class="search" />-->
<!---->
<!--    <select class="select" style="min-width: 311px">-->
<!--        <option selected>Текущий вайп</option>-->
<!--        <option value="1">Вайп 18.11.24–02.12.24</option>-->
<!--        <option value="2">Вайп 28.10.24–18.11.24</option>-->
<!--        <option value="3">Вайп 07.10.24–28.10.24</option>-->
<!--    </select>-->
<!--</div>-->

<nav class="servers_navbar mb-24">
    <ul class="servers_navbar_nav">
        <?php foreach ($servers as $_server): ?>
            <li>
                <a href="<?=$_server->getLink('user-stats', $user->steam_id)?>" class="servers_navbar_nav_item<?php if ($_server->id === $server->id): ?> active<?php endif; ?> ">
                    <?=Yii::t('database', $_server->monitoring_name)?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?= Alert::widget() ?>
<div class="flex flex-column gap-x-12 gap-y-12 tab-pane active" id="Max3">
    <div class="page-stats__two-blocks">
        <?php
        $userProfile = $user->userProfile;
        $isOwner = !Yii::$app->user->isGuest && Yii::$app->user->identity->id === $user->id;
        ?>
        <?=Yii::$app->view->render('profile.twig', [
            'WRAPPER_CLASS' => $statusClass,
            'USER' => $user,
            'IS_GUEST' => $IS_GUEST,
            'IS_OWNER' => $isOwner,
            'IS_HIDDEN' => $isHidden,
            'YOUTUBE_LINK' => $userProfile ? $userProfile->youtube_link : null,
            'TWITCH_LINK' => $userProfile ? $userProfile->twitch_link : null,
            'VK_LINK' => $userProfile ? $userProfile->vk_link : null,
            'TELEGRAM_LINK' => $userProfile ? $userProfile->telegram_link : null,
            'STATS' => [
                'PLAYTIME' => Statistics::getParam($player, 'playtime'),
                'ONLINE' => Servers::getPlayTime(Statistics::getParam($player, 'playtime')),
                'KILLS' => number_format(Statistics::getParam($player, 'kills'), 0),
                'DEATHS' => number_format(Statistics::getParam($player, 'deaths'), 0),
                'KD' => number_format($kdr, 2),
                'SCIENTISTS' => number_format(Statistics::getParam($player, 'scientists'), 0),
                'WOUNDED' => number_format(Statistics::getParam($player, 'wounded'), 0),
                'TCS_DESTOYED' => number_format(Statistics::getParam($player, 'tcsdestroyed'), 0),
                'WIPES' => number_format($wipes, 0),
                'NUDE_KILLS' => number_format(Statistics::getParam($player, 'nude_kills'), 0),
            ],
        ]);?>
        <?=$this->render('_player_stats_farm', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>
    </div>
    <div class="page-stats__two-blocks">
       <div class="page-stats__categories__blocks_wrap w-50p">
           <?=Yii::$app->view->render('awards.twig', [
               'ITEMS' => $awards,
               'AWARDS_STATS' => $awardsStats,
           ]);?>
           <?=$this->render('_player_stats_stats_blocks', [
               'images' => $images,
               'names' => $names,
               'player' => $player,
               'server' => $server,
               'steamId' => $steamId,
               'user' => $user,
           ]);?>
       </div>
    </div>

    <div class="flex flex-column gap-x-12 gap-y-12">

        <div class="page-stats__two-blocks">
            <?=$this->render('_player_stats_weapons', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>

            <?=$this->render('_player_stats_reider', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>
        </div>

<!--        <div class="page-stats__two-blocks">-->
<!--            <section class="page-stats__block-without-hover w-50p">-->
<!--                <header class="flex items-center justify-space-between mb-24 transition-all">-->
<!--                    <h4 class="flex items-center gap-x-12">-->
<!--                        Статистика по ресурсам<span-->
<!--                            class="icons icons_24px icons_24px_info icons_hover"-->
<!--                            data-bs-toggle="tooltip"-->
<!--                            data-bs-placement="right"-->
<!--                            data-bs-title="У каждого оружия указано количество убитых"-->
<!--                        ></span>-->
<!--                    </h4>-->
<!---->
<!--                    <label class="page-stats__show-statistics-block">-->
<!--                      <p class="p1 text-text-teritiary">Показывать</p>-->
<!--                      <input checked type="checkbox" class="show-statistics-block__switch none" />-->
<!--                      <span>-->
<!--                        <span class="icons icons_switch icons_switch_on"></span>-->
<!--                        <span class="icons icons_switch icons_switch_off"></span>-->
<!--                      </span>-->
<!--                    </label>-->
<!--                </header>-->
<!---->
<!--                <img src="/images/design/stats/graphics_3.png" class="w-full" alt="graphics_3" />-->
<!--            </section>-->
<!---->
<!--            <section class="page-stats__block-without-hover w-50p">-->
<!--                <header class="flex items-center justify-space-between mb-24 transition-all">-->
<!--                    <h4 class="flex items-center gap-x-12">-->
<!--                        Статистика по ящикам и бочкам<span-->
<!--                            class="icons icons_24px icons_24px_info icons_hover"-->
<!--                            data-bs-toggle="tooltip"-->
<!--                            data-bs-placement="right"-->
<!--                            data-bs-title="У каждого оружия указано количество убитых"-->
<!--                        ></span>-->
<!--                    </h4>-->
<!---->
<!--                  <label class="page-stats__show-statistics-block">-->
<!--                      <p class="p1 text-text-teritiary">Показывать</p>-->
<!--                      <input checked type="checkbox" class="show-statistics-block__switch none" />-->
<!--                      <span>-->
<!--                        <span class="icons icons_switch icons_switch_on"></span>-->
<!--                        <span class="icons icons_switch icons_switch_off"></span>-->
<!--                      </span>-->
<!--                    </label>-->
<!--                </header>-->
<!---->
<!--                <img src="/images/design/stats/graphics_3.png" class="w-full" alt="graphics_3" />-->
<!--            </section>-->
<!--        </div>-->

        <?=$this->render('_player_stats_fishing', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>

        <div class="page-stats__two-blocks">
            <?=$this->render('_player_stats_ferm', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>
            <?=$this->render('_player_stats_food', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>
        </div>

        <?=$this->render('_player_stats_tea', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>

        <?=$this->render('_player_stats_pie', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>

        <?=$this->render('_player_stats_hunter', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>

        <div class="page-stats__two-blocks">
            <?=$this->render('_player_stats_level_card', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>

            <?=$this->render('_player_stats_medical', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>
        </div>

    </div>
</div>
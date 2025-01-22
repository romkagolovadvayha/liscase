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

if (empty($user)) {
    throw new NotFoundHttpException(Yii::t('common', 'Пользователь не найден или статистика еще не подгрузилась!'));
}

$this->title = $user->username . " " . Yii::t('common', 'статистика на сервере') . " " . Yii::t('database', $server->name);
$this->params['page'] = 'stats';

$wipe = $server->currentWipe();
$player = Statistics::getPlayerStats($server, $user->steam_id, $wipe);
$images = Statistics::productsImages();
$names = Statistics::productsNames();

$statusClass = $user->getStatus() ? '' : ' profile_offline';
if ($user->status === User::STATUS_BLOCKED) {
    $statusClass = ' profile_banned';
}
$awards = \common\models\tasks\Task::awards($user->id);
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


<?= Alert::widget() ?>
<div class="flex flex-column gap-x-12 gap-y-12 tab-pane active" id="Max3">
    <div class="flex justify-space-between gap-x-12">
        <div class="flex flex-column w-full gap-y-12">
            <section class="page-stats__block profile">
                <!-- ОНЛАЙН -->
                <div class="profile__wrapper<?=$statusClass?>">
                    <div class="profile__image">
                        <img src="<?=$user->getAvatar()?>" alt="<?=Yii::t('common', 'Аватар игрока')?> <?=$user->username?>" />
                    </div>
                    <div>
                        <h2 class="text-primary-colors-main flex items-center gap-x-12">
                            <?=$user->username?> <a href="https://steamcommunity.com/profiles/<?=$user->steam_id?>" class="stats_player_card_body_name_steam" target="_blank" title="<?=Yii::t('common', 'Перейти в профиль Steam')?>"><span class="icons icons_24px icons_24px_steam"></span></a>
                        </h2>
                        <p class="p1 text-text-main">
                            <?=Yii::t('common', 'Онлайн за вайп')?>: <span style="color: var(--online);"><?=Servers::getPlayTime(Statistics::getParam($player, 'playtime'))?></span>
                        </p>
                    </div>
                </div>

                <?php if (!empty($user->stat_status)): ?>
                    <p class="p1 text-text-secondary mb-8 relative z-1"><?=Yii::t('common', 'Статус')?></p>
                    <p class="p1 text-text-teritiary relative z-1"><?=$user->stat_status?></p>
                <?php endif; ?>

                <a href="<?=$user->getLink('report')?>" class="flex items-center gap-x-8 mt-20 relative z-1 show-modal-link"
                   data-size="modal-lg"
                   data-toggl="modal"
                   data-target="modal-dialog"
                   data-title="<?=Yii::t('common', 'Пожаловаться на игрока')?>">
                    <span class="icons icons_24px icons_24px_report"></span>
                    <?=Yii::t('common', 'Пожаловаться')?>
                </a>
            </section>

            <!-- Награды -->
            <section class="page-stats__block-without-hover">
                <h4 class="flex items-center gap-x-12 mb-24">
                    Награды<span
                        class="icons icons_24px icons_24px_info icons_hover"
                        data-bs-toggle="tooltip"
                        data-bs-placement="right"
                        data-bs-title="<?=Yii::t('common', 'Выполни все задания, чтобы получить награду')?>"
                    ></span>
                </h4>

                <div class="page-stats__awards">
                    <?php if (empty($awards)): ?>
                        <?=Yii::t('common', 'Игрок не выполнил еще не одного задания.')?>
                    <?php endif; ?>
                    <?php foreach ($awards as $item): ?>
                        <div class="award">
                            <img src="<?=$item['image']?>" alt="<?=$item['name']?>" class="award__image" />
                            <p class="p2"><?=$item['name']?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!--Статистика по убийствам и смертям-->
<!--            <section class="page-stats__block">-->
<!--                <h4 class="flex items-center gap-x-12 mb-24">-->
<!--                    Статистика по убийствам и смертям<span-->
<!--                        class="icons icons_24px icons_24px_info icons_hover"-->
<!--                        data-bs-toggle="tooltip"-->
<!--                        data-bs-placement="right"-->
<!--                        data-bs-title="У каждого оружия указано количество убитых"-->
<!--                    ></span>-->
<!--                </h4>-->
<!---->
<!--                <img src="/images/design/stats/graphics_2.png" alt="" class="w-full" />-->
<!--            </section>-->

            <?=$this->render('_player_stats_stats_blocks', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>
        </div>

        <div class="flex flex-column w-full gap-y-12">
            <?=$this->render('_player_stats_stats', [
                'images' => $images,
                'names' => $names,
                'player' => $player,
                'server' => $server,
                'steamId' => $steamId,
                'user' => $user,
            ]);?>

<!--            <section class="page-stats__block">-->
<!--                <h4 class="flex items-center gap-x-12 mb-24">-->
<!--                    Наигранные часы<span-->
<!--                            class="icons icons_24px icons_24px_info icons_hover"-->
<!--                            data-bs-toggle="tooltip"-->
<!--                            data-bs-placement="right"-->
<!--                            data-bs-title="У каждого оружия указано количество убитых"-->
<!--                    ></span>-->
<!--                </h4>-->
<!---->
<!--                <img src="/images/design/stats/graphics_1.png" alt="" class="w-full" />-->
<!--            </section>-->

            <?=$this->render('_player_stats_hits', [
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

        <?=$this->render('_player_stats_farm', [
            'images' => $images,
            'names' => $names,
            'player' => $player,
            'server' => $server,
            'steamId' => $steamId,
            'user' => $user,
        ]);?>

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
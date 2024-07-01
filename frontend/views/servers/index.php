<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

use common\models\servers\Servers;

$this->title = Yii::t('common', 'Сервера для Rust');

\frontend\assets\LastDropAsset::register($this);

/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->all();

$formatJs = <<< 'JS'
var wipes = $('.server_info_profile_wipe_date');
for (var i = 0; i < wipes.length; i++) {
    var date = $(wipes[i]).attr('data-date');
    $(wipes[i]).html(moment(date).locale('ru').format('D MMM YYYY в HH:mm МСК'));
}
JS;
$this->registerJs($formatJs, \yii\web\View::POS_END);
?>
<?php //echo $this->render('@frontend/views/layouts/_promocode_line', [
//    'promocodeForm' => $promocodeForm
//]); ?>

<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
<!--                <div class="page_content">-->
                    <div class="server_info_wrap">
                    <?php foreach ($servers as $server): ?>
                        <div class="server_info">
                            <div class="server_info_profile">
                                <div class="server_info_profile_title"><?=Yii::t('database', $server->name)?></div>
                                <div class="server_info_profile_wipe_wrap">
                                    <?php if (!empty($server->wipe)): ?>
                                    <div class="server_info_profile_wipe">
                                        <span class="server_info_profile_wipe_label"><?=Yii::t('common', 'Предыдущий вайп')?>:</span>
                                        <span class="server_info_profile_wipe_date" data-date="<?=$server->wipe?>"><?=$server->wipe?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($server->next_wipe)): ?>
                                    <div class="server_info_profile_wipe">
                                        <span class="server_info_profile_wipe_label"><?=Yii::t('common', 'Следующий вайп')?>:</span>
                                        <span class="server_info_profile_wipe_date" data-date="<?=$server->next_wipe?>"><?=$server->next_wipe?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($server->global_wipe)): ?>
                                    <div class="server_info_profile_wipe">
                                        <span class="server_info_profile_wipe_label"><?=Yii::t('common', 'Глобал вайп')?>:</span>
                                        <span class="server_info_profile_wipe_date" data-date="<?=$server->global_wipe?>"><?=$server->global_wipe?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="server_info_profile_server_info">
                                    <div class="server_info_profile_server_info_ip">
                                        <?=$server->ip?>:<?=$server->port?>
                                    </div>
                                    <div class="server_info_profile_server_info_online">
                                        <div class="server_info_profile_server_info_online_players">
                                            <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                                                <span class="server_info_profile_server_info_online_players_players" title="<?=Yii::t('common', 'Игроков на сервере')?>"><?=$server->players + $server->joined?></span><span>/</span><span><?=$server->max?></span>
                                            <?php else: ?>
                                                <span class="server_info_profile_server_info_online_players_players"><?=Yii::t('common', 'Выключен')?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                if ($server->players+$server->joined > 0) {
                                    $percentPlayers         = ceil(100 / $server->max * $server->players);
                                    $percentJoined          = ceil(100 / $server->max * $server->joined);
                                    $percentQueued          = ceil(100 / $server->max * $server->queued);
                                    $percentAbsoluteCount   = 100 / ($percentPlayers + $percentJoined);
                                    $percentPlayersAbsolute = ceil($percentAbsoluteCount * $percentPlayers);
                                    $percentJoinedAbsolute  = ceil($percentAbsoluteCount * $percentJoined);
                                    $percentQueuedAbsolute  = ceil($percentAbsoluteCount * $percentQueued);
                                } else {
                                    $percentPlayers = 0;
                                    $percentJoined = 0;
                                    $percentQueued = 0;
                                    $percentAbsoluteCount = 0;
                                    $percentPlayersAbsolute = 0;
                                    $percentJoinedAbsolute = 0;
                                    $percentQueuedAbsolute = 0;
                                }
                                ?>
                                <div class="server_info_profile_item btn-clipboard"
                                     data-bs-toggle="tooltip"
                                     data-bs-placement="bottom"
                                     data-bs-title="<?=Yii::t('common', 'Скопировать IP адрес')?>"
                                     data-clipboard-text="connect <?=$server->ip?>:<?=$server->port?>"
                                     data-message="<?=Yii::t('common', 'IP адрес скопирован в буфер обмена!')?>">
                                    <div class="server_info_profile_item_progress_wrap">
                                        <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                                            <div class="server_info_profile_item_progress_players_wrap" style="width: <?=$percentPlayers+$percentJoined?>%">
                                                <div class="server_info_profile_item_progress_players" style="width: <?=$percentPlayersAbsolute + $percentJoinedAbsolute?>%"></div>
                                                <div class="server_info_profile_item_progress_queued" style="width: <?=$percentQueuedAbsolute?>%"></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="server_info_profile_item_progress_players_wrap" style="width: 100%">
                                                <div class="server_info_profile_item_progress_offline" style="width: 100%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="/servers/rules?server=<?=$server->tag?>" class="server_info_profile_link"><span><?=Yii::t('common', 'Правила сервера')?></span></a>
                                <a href="https://rustmaps.com/map/<?=$server->map?>" target="_blank" class="server_info_profile_link"><span><?=Yii::t('common', 'Текущая карта')?></span></a>
                            </div>
                            <div class="server_info_description">
                                <?=Yii::t('database', trim($server->description))?>
                                <?php if ($server->stats_payment): ?>
                                <div class="server_info_description_success"><span class="server_info_description_success_icon"><i class="fas fa-check"></i></span> <?=Yii::t('common', 'Оплата за первые места в')?> <a href="/stats?server=<?=$server->tag?>"><?=Yii::t('common', 'статистике')?></a></div>
                                <?php endif; ?>
                                <?php if ($server->skindrops): ?>
                                <div class="server_info_description_success"><span class="server_info_description_success_icon"><i class="fas fa-check"></i></span> <?=Yii::t('common', 'Розыгрыш скинов каждый час!')?></div>
                                <?php endif; ?>
                                <div class="server_info_description_success"><span class="server_info_description_success_icon"><i class="fas fa-check"></i></span> <?=Yii::t('common', 'Мощное железо на сервере')?> <b>i9-14900K 5.7Ghz</b></div>
                                <div class="server_info_description_success"><span class="server_info_description_success_icon"><i class="fas fa-check"></i></span> <?=Yii::t('common', 'Лимит по команде')?> <b><?=!empty($server->team_limit) ? $server->team_limit . ' ' . Yii::t('common', 'человека') : Yii::t('common', 'не ограничено')?></b>!</div>
                                <div class="server_info_description_success"><span class="server_info_description_success_icon"><i class="fas fa-check"></i></span> <?=Yii::t('common', 'Размер карты')?> <?=explode('_', $server->map)[0]?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
<!--                </div>-->
            </div>
        </main>
    </div>
</div>
<?php

use common\models\servers\Servers;

/** @var Servers[] $servers */
$servers = Servers::find()
    ->cache(30)
    ->all();

$lang = substr(Yii::$app->language, 0, 2);
$this->registerJs(
    <<<JS
        var timers = $('.server_timer');
        for (var i = 0; i < timers.length; i++) {
            var dateTime = $(timers[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(timers[i]).html(left.locale('{$lang}').fromNow());
        }
JS
);

?>

<div class="servers_wrapper">
    <div class="servers">
        <div class="servers_header">
            <h2><?=Yii::t('common', 'Мониторинг')?></h2>
        </div>
        <div class="servers_body">
            <?php foreach ($servers as $server): ?>
                <?php
                if ($server->players+$server->joined > 0) {
                    $percentPlayers = ceil(100/$server->max*$server->players);
                    $percentJoined = ceil(100/$server->max*$server->joined);
                    $percentQueued = ceil(100/$server->max*$server->queued);
                    $percentAbsoluteCount = 100/($percentPlayers+$percentJoined);
                    $percentPlayersAbsolute = ceil($percentAbsoluteCount * $percentPlayers);
                    $percentJoinedAbsolute = ceil($percentAbsoluteCount * $percentJoined);
                    $percentQueuedAbsolute = ceil($percentAbsoluteCount * $percentQueued);
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
                <div class="servers_item">
                    <div class="servers_item_header">
                        <div class="servers_item_header_content">
                            <div class="servers_item_header_content_name"><?=Yii::t('database', trim($server->name))?></div>
                            <div class="servers_item_header_content_footer">
                                <span class="server_timer" data-time="<?=strtotime($server->wipe)?>"><?=$server->wipe?></span>
                                <span class="servers_item_header_content_footer_tag">
                                    <?php if ($server->wipe_type === 7): ?>
                                        <?=Yii::t('common', 'Еженедельно')?>
                                    <?php elseif ($server->wipe_type === 14): ?>
                                        <?=Yii::t('common', 'Каждые две недели')?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="servers_item_header_players">
                            <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                                <span class="servers_item_header_players_players" title="<?=Yii::t('common', 'Игроков на сервере')?>"><?=$server->players + $server->joined?></span><span>/</span><span><?=$server->max?></span>
                            <?php else: ?>
                                <span class="servers_item_header_players_players"><?=Yii::t('common', 'Выключен')?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="servers_item_progress_wrap">
                        <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                            <div class="servers_item_progress_players_wrap" style="width: <?=$percentPlayers+$percentJoined?>%">
                                <div class="servers_item_progress_players" style="width: <?=$percentPlayersAbsolute + $percentJoinedAbsolute?>%"></div>
                                <div class="servers_item_progress_queued" style="width: <?=$percentQueuedAbsolute?>%"></div>
                            </div>
                        <?php else: ?>
                            <div class="servers_item_progress_players_wrap" style="width: 100%">
                                <div class="servers_item_progress_offline" style="width: 100%"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="servers_item_hover">
                        <div class="servers_item_hover_wrap">
                            <div class="btn-clipboard"
                                 data-clipboard-text="connect <?=$server->ip?>:<?=$server->port?>"
                                 data-message="<?=Yii::t('common', 'IP адрес скопирован в буфер обмена!')?>">
                                IP: <?=$server->ip?>:<?=$server->port?> <i class="fas fa-copy"></i>
                            </div>
                            <div><?=Yii::t('common', 'Вайп был')?> <span class="server_timer" data-time="<?=strtotime($server->wipe)?>"><?=$server->wipe?></span></div>
                            <a class="servers_item_hover_button" href="/servers/rules?server=<?=$server->tag?>"><?=Yii::t('common', 'Правила сервера')?></a>
                            <a class="servers_item_hover_button" href="https://rustmaps.com/map/4250_777<?=$server->map?>" target="_blank"><?=Yii::t('common', 'Текущая карта')?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="servers_link_wrap">
                <a href="/servers" class="servers_link"><span><?=Yii::t('common', 'Подробнее')?></span></a>
            </div>
        </div>
    </div>
</div>

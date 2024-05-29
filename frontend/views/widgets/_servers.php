<?php

use common\models\servers\Servers;

/** @var Servers[] $servers */
$servers = Servers::find()
    ->cache(30)
    ->all();

?>

<div class="servers_wrapper">
    <div class="servers">
        <div class="servers_body">
            <?php foreach ($servers as $server): ?>
                <?php
                    $percentPlayers = ceil(100/$server->max*$server->players);
                    $percentJoined = ceil(100/$server->max*$server->joined);
                    $percentQueued = ceil(100/$server->max*$server->queued);
                    $percentAbsoluteCount = 100/($percentPlayers+$percentJoined);
                    $percentPlayersAbsolute = ceil($percentAbsoluteCount * $percentPlayers);
                    $percentJoinedAbsolute = ceil($percentAbsoluteCount * $percentJoined);
                    $percentQueuedAbsolute = ceil($percentAbsoluteCount * $percentQueued);
                ?>
                <div class="servers_item btn-clipboard"
                     data-bs-toggle="tooltip"
                     data-bs-placement="bottom"
                     data-bs-title="<?=Yii::t('common', 'Скопировать IP адрес')?>"
                     data-clipboard-text="connect <?=$server->ip?>:<?=$server->port?>"
                     data-message="<?=Yii::t('common', 'IP адрес скопирован в буфер обмена!')?>">
                    <div class="servers_item_header">
                        <div class="servers_item_header_name"><?=$server->name?></div>
<!--                        <div class="servers_item_header_address">--><?php //echo $server->ip?><!--:--><?php //echo $server->port?><!--</div>-->
                        <div class="servers_item_header_players">
                            <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                                <span class="servers_item_header_players_players" title="Игроков на сервере"><?=$server->players + $server->joined?></span><span>/</span><span><?=$server->max?></span>
                            <?php else: ?>
                                <span class="servers_item_header_players_players">Выключен</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="servers_item_progress_wrap">
                        <?php if ($server->status === Servers::STATUS_ACTIVE): ?>
                            <div class="servers_item_progress_players_wrap" style="width: <?=$percentPlayers+$percentJoined?>%">
                                <div class="servers_item_progress_players" style="width: <?=$percentPlayersAbsolute?>%"></div>
                                <div class="servers_item_progress_joined" style="width: <?=$percentJoinedAbsolute?>%"></div>
                                <div class="servers_item_progress_queued" style="width: <?=$percentQueuedAbsolute?>%"></div>
                            </div>
                        <?php else: ?>
                            <div class="servers_item_progress_players_wrap" style="width: 100%">
                                <div class="servers_item_progress_offline" style="width: 100%"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="/servers" class="servers_link"><span>Наши сервера</span></a>
        </div>
    </div>
</div>

<?php

use common\models\servers\Servers;

/** @var Servers[] $servers */
$servers = Servers::find()
    ->cache(30)
    ->all();

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
                <div class="servers_item btn-clipboard"
                     data-bs-toggle="tooltip"
                     data-bs-placement="bottom"
                     data-bs-title="<?=Yii::t('common', 'Скопировать IP адрес')?>"
                     data-clipboard-text="connect <?=$server->ip?>:<?=$server->port?>"
                     data-message="<?=Yii::t('common', 'IP адрес скопирован в буфер обмена!')?>">
                    <div class="servers_item_header">
                        <div class="servers_item_header_name"><?=Yii::t('database', trim($server->name))?></div>
<!--                        <div class="servers_item_header_address">--><?php //echo $server->ip?><!--:--><?php //echo $server->port?><!--</div>-->
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
                </div>
            <?php endforeach; ?>
            <div class="servers_link_wrap">
                <a href="/servers" class="servers_link"><span><?=Yii::t('common', 'Подробнее')?></span></a>
            </div>
        </div>
    </div>
</div>

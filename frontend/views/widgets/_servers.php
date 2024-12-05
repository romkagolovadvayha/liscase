<?php

use common\models\servers\Servers;

/** @var Servers[] $servers */
$servers = Servers::find()
    ->cache(30)
    ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
    ->orderBy(['sort' => SORT_ASC])
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
                <div class="servers_item server_item_js server_status<?=$server->status?>" data-server-id="<?=$server->id?>">
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
                                    <?php elseif ($server->wipe_type === 30): ?>
                                        <?=Yii::t('common', 'Раз в месяц')?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="servers_item_header_players">
                            <span class="players_wrap_js"><span class="servers_item_header_players_players players_js" title="<?=Yii::t('common', 'Игроков на сервере')?>"><?=$server->players + $server->joined?></span><span>/</span><span><?=$server->max?></span></span>
                            <span class="servers_item_header_players_players wait_js"><?=Yii::t('common', 'Скоро')?></span>
                            <span class="servers_item_header_players_players offline_js"><?=Yii::t('common', 'Выключен')?></span>
                        </div>
                    </div>
                    <div class="servers_item_progress_wrap">
                        <div class="servers_item_progress_players_wrap progress_js" style="width: <?=$percentPlayers+$percentJoined?>%">
                            <div class="servers_item_progress_players players_progress_js" style="width: <?=$percentPlayersAbsolute + $percentJoinedAbsolute?>%"></div>
                            <div class="servers_item_progress_queued queued_progress_js" style="width: <?=$percentQueuedAbsolute?>%"></div>
                        </div>
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
                            <a class="servers_item_hover_button" href="https://rustmaps.com/map/<?=$server->map?>" target="_blank"><?=Yii::t('common', 'Текущая карта')?></a>
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

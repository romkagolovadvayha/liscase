<?php

use common\models\servers\Servers;
use common\models\stats\Teams;

/** @var array $player */
/** @var string $steam_id */
/** @var array $models */
/** @var string $title */
/** @var Servers $server */
/** @var Teams $clan */

?>

<div class="stats_player_clan_wrap">
    <div class="stats_player_clan_header"><?=$title?></div>
    <div class="stats_player_clan">
        <?php foreach ($clan as $model): ?>
            <?php
            $link = "";
            if (empty($model['name'])) {
                $link = "<span class=\"stats_player_clan_item_desc_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['steam_id'] === $steam_id) {
                $link = "<span class=\"stats_player_clan_item_desc_name\">{$model['name']}</span>";
            } else {
                $link = "<a title=\"" . Yii::t('common', 'Открыть профиль') . "\" class=\"link_name stats_player_clan_item_desc_name\" href=\"/stats/player?steamId={$model['steam_id']}&server={$server->tag}\">
                    {$model['name']}
                </a>";
            }
            ?>
            <div class="stats_player_clan_item">
                <div class="stats_player_clan_item_desc">
                        <?=$link?>
                </div>
                <div class="stats_player_clan_item_date">
                    <span><?=Yii::t('common', 'Дата вступления')?>: <?=date('d.m.Y H:i:s', strtotime($model['created_at']))?></span>
                    <?php if ($model['team_author']): ?>
                    <span class="stats_player_clan_item_author"><?=Yii::t('common', 'Владелец клана')?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

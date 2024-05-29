<?php

use common\models\servers\Servers;
use common\models\stats\Teams;

/** @var array $player */
/** @var array $models */
/** @var string $title */
/** @var Servers $server */
/** @var Teams $teams */

?>

<div class="stats_player_teams_wrap">
    <div class="stats_player_teams_header"><?=$title?></div>
    <div class="stats_player_teams">
        <?php foreach ($teams as $model): ?>
            <?php
            $authorLink = "";
            if (empty($model['team_author_name'])) {
                $authorLink = "<span class=\"stats_player_teams_item_desc_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['team_author'] === $player['steamid']) {
                $authorLink = "<span class=\"stats_player_teams_item_desc_name\">{$model['team_author_name']}</span>";
            } else {
                $authorLink = "<a title=\"" . Yii::t('common', 'Открыть профиль') . "\" class=\"link_name stats_player_teams_item_desc_name\" href=\"/stats/player?steamId={$model['team_author']}&server={$server->tag}\">
                    {$model['team_author_name']}
                </a>";
            }
            ?>
            <?php
            $link = "";
            if (empty($model['name'])) {
                $link = "<span class=\"stats_player_teams_item_desc_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['steam_id'] === $player['steamid']) {
                $link = "<span class=\"stats_player_teams_item_desc_name\">{$model['name']}</span>";
            } else {
                $link = "<a title=\"" . Yii::t('common', 'Открыть профиль') . "\" class=\"link_name stats_player_teams_item_desc_name\" href=\"/stats/player?steamId={$model['steam_id']}&server={$server->tag}\">
                    {$model['name']}
                </a>";
            }
            ?>
            <div class="stats_player_teams_item">
                <div class="stats_player_teams_item_desc">
                    <?php if ($model['type'] === Teams::TYPE_INVITE_ACCEPTED): ?>
                        <?=$link?>
                        <span class="stats_player_teams_item_desc_accepted"><?=Yii::t('common', 'вступил к')?></span>
                        <?=$authorLink?>
                    <?php endif; ?>
                    <?php if ($model['type'] === Teams::TYPE_KICKED): ?>
                        <?=$authorLink?>
                        <span class="stats_player_teams_item_desc_kicked"><?=Yii::t('common', 'выгнал')?></span>
                        <?=$link?>
                    <?php endif; ?>
                    <?php if ($model['type'] === Teams::TYPE_LEAVED): ?>
                        <?php if ($model['team_author'] === $model['steam_id']): ?>
                            <?=$link?>
                            <span class="stats_player_teams_item_desc_leaved"><?=Yii::t('common', 'удалил команду')?></span>
                        <?php else: ?>
                                <?=$link?>
                                <span class="stats_player_teams_item_desc_leaved"><?=Yii::t('common', 'ливнул от')?></span>
                                <?=$authorLink?>
                        <?php endif; ?>
                <?php endif; ?>
                </div>
                <div class="stats_player_teams_item_date">
                    <span><?=date('d.m.Y H:i:s', strtotime($model['created_at']))?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php

use common\models\servers\Servers;
use common\models\stats\Kills;

/** @var array $player */
/** @var array $models */
/** @var string $title */
/** @var Servers $server */

$models = Kills::getKills($server, $player, $models);
$animals = Kills::getAnimalsList();
$animals2 = Kills::getAnimals2List();
?>

<div class="stats_player_kills_wrap">
    <div class="stats_player_kills_header"><?=$title?></div>
    <div class="stats_player_kills">
        <?php foreach ($models as $model): ?>
            <?php
            $deadLink = "";
            if (empty($model['dead_name'])) {
                $deadLink = "<span class=\"stats_player_kills_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['dead'] === $player['steamid']) {
                $deadLink = "<span class=\"stats_player_kills_name\">{$model['dead_name']}</span>";
            } else {
                $deadLink = "<a title=\"" . Yii::t('common', 'Открыть профиль') . "\" class=\"link_name stats_player_kills_name\" href=\"/stats/player?steamId={$model['dead']}&server={$server->tag}\">
                    {$model['dead_name']}
                </a>";
            }
            ?>
            <?php
            $link = "";
            if (empty($model['name'])) {
                $link = "<span class=\"stats_player_kills_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['steam_id'] === $player['steamid']) {
                $link = "<span class=\"stats_player_kills_name\">{$model['name']}</span>";
            } else {
                $link = "<a title=\"" . Yii::t('common', 'Открыть профиль') . "\" class=\"link_name stats_player_kills_name\" href=\"/stats/player?steamId={$model['steam_id']}&server={$server->tag}\">
                    {$model['name']}
                </a>";
            }
            ?>
            <div class="live_items_item">
                <?php if (!empty($model['weapon_image'])): ?>
                    <img width="30px" title="<?=$model['weapon']?>" src="<?=$model['weapon_image']?>"/>
                <?php endif; ?>
                <?php if ($model['type'] === 'suicides'): ?>
                    <?=$link?>
                    <span>совершил самоубийство</span>
                <?php endif; ?>
                <?php if ($model['type'] === 'animal'): ?>
                    <?=$link?>
                    <span>убил</span>
                    <span><?=$animals2[$model['dead']]?></span>
                <?php endif; ?>
                <?php if ($model['type'] === 'deaths'): ?>
                    <span><?=$animals[$model['dead']]?></span>
                    <span>убил</span>
                    <?=$link?>
                <?php endif; ?>
                <?php if ($model['type'] === 'scientists'): ?>
                    <?=$link?>
                    <span>убил</span>
                    <img width="30px" src="<?=$model['image']?>"/>
                    <span>бота</span>
                <?php endif; ?>
                <?php if ($model['type'] === 'kill'): ?>
                    <?php if (empty($model['image'])): ?>
                        <?=$link?>
                    <?php else: ?>
                        <span>Бот</span>
                        <img width="30px" src="<?=$model['image']?>"/>
                    <?php endif; ?>
                    <span>убил</span>
                    <?=$deadLink?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;

/** @var \common\models\user\User $user */
/** @var array $models */
/** @var string $title */
/** @var Servers $server */

$models = Kills::getKills($server, $user);
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
                $deadLink = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['dead'] === $user->steam_id) {
                $deadLink = "<span class=\"stats_player_kills_item_name\">{$model['dead_name']}</span>";
            } else {
                $deadLink = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"link_name stats_player_kills_name\" href=\"/stats/player?steamId={$model['dead']}&server={$server->tag}\">
                    {$model['dead_name']}
                </a>";
            }
            ?>
            <?php
            $link = "";
            if (empty($model['name'])) {
                $link = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
            } elseif ($model['steam_id'] === $user->steam_id) {
                $link = "<span class=\"stats_player_kills_item_name\">{$model['name']}</span>";
            } else {
                $link = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"link_name stats_player_kills_item_name\" href=\"/stats/player?steamId={$model['steam_id']}&server={$server->tag}\">
                    {$model['name']}
                </a>";
            }
            ?>
            <div class="stats_player_kills_item">
                <?php if (!empty($model['weapon_image'])): ?>
                    <img width="30px" title="<?=!empty($model['weapon_name']) ? $model['weapon_name'] : $model['weapon']?>" src="<?=$model['weapon_image']?>"/>
                <?php endif; ?>
                <?php if ($model['type'] === 'suicides'): ?>
                    <?=$link?>
                    <span><?=Yii::t('common', 'совершил самоубийство')?></span>
                <?php endif; ?>
                <?php if ($model['type'] === 'animal'): ?>
                    <?=$link?>
                    <span><?=Yii::t('common', 'убил')?></span>
                    <?php if (!empty($model['distance'])): ?>
                        <span class="stats_player_kills_item_distance" title="<?=Yii::t('common', 'Дистанция')?>"><?=$model['distance']?>m</span>
                    <?php endif; ?>
                    <span><?=$animals2[$model['dead']]?></span>
                <?php endif; ?>
                <?php if ($model['type'] === 'deaths'): ?>
                    <span><?=$animals[$model['dead']]?></span>
                    <span><?=Yii::t('common', 'убил')?></span>
                    <?=$link?>
                <?php endif; ?>
                <?php if ($model['type'] === 'scientists'): ?>
                    <?=$link?>
                    <span><?=Yii::t('common', 'убил')?></span>
                    <?php if (!empty($model['distance'])): ?>
                        <span class="stats_player_kills_item_distance" title="<?=Yii::t('common', 'Дистанция')?>"><?=$model['distance']?>m</span>
                    <?php endif; ?>
                    <img width="30px" src="<?=$model['image']?>"/>
                    <span><?=Yii::t('common', 'бота')?></span>
                <?php endif; ?>
                <?php if ($model['type'] === 'kill'): ?>
                    <?php if (empty($model['image'])): ?>
                        <?=$link?>
                    <?php else: ?>
                        <span><?=Yii::t('common', 'Бот')?></span>
                        <img width="30px" src="<?=$model['image']?>"/>
                    <?php endif; ?>
                    <span><?=Yii::t('common', 'убил')?></span>
                    <?php if (!empty($model['distance'])): ?>
                        <span class="stats_player_kills_item_distance" title="<?=Yii::t('common', 'Дистанция')?>"><?=$model['distance']?>m</span>
                    <?php endif; ?>
                    <?=$deadLink?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php

/** @var Servers $server */

use common\models\servers\Servers;
use common\models\statistics\Statistics;

$stats = Statistics::getStats($server);

if (empty($stats['playtime']) || empty($stats['playtime']['players'])) {
    return;
}

$reider = Statistics::getTopWidgetItem('reider', $stats);
$hunter = Statistics::getTopWidgetItem('hunter', $stats);
$fermer = Statistics::getTopWidgetItem('fermer', $stats);
$farmer = Statistics::getTopWidgetItem('farmer', $stats);
$fishing = Statistics::getTopWidgetItem('fishing', $stats);
$playtime = Statistics::getTopWidgetItem('playtime', $stats);
$scientists = Statistics::getTopWidgetItem('scientists', $stats);
$killer = Statistics::getTopWidgetItem('kills', $stats);

?>

<div class="top_table">
    <?php if (!empty($reider)): ?>
        <?php if ($server != 'pve'): ?>
        <div class="top_table_item">
            <div class="top_table_item_image">
                <img src="<?=$reider['user']->getAvatar()?>" alt="<?=$reider['user']->username?>"/>
            </div>
            <div class="top_table_item_wrap">
                <div class="top_table_item_header">
                    <div class="top_table_item_header_name">
                        <?=Yii::t('common', 'РЕЙДЕР')?>
                    </div>
                    <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                        +500 RUB
                    </div>
                </div>
                <div class="top_table_item_body">
                    <div class="top_table_item_body_link">
                        <a target="#" href="/stats/player?steamId=<?=$reider['steam_id']?>&server=<?=$server->tag?>"><?=$reider['user']->username?></a>
                    </div>
                    <div class="top_table_item_body_score">
                        <?=round($reider['total_score'])?>
                    </div>
                </div>
            </div>
        </div>
        <div class="top_table_item">
            <div class="top_table_item_image">
                <img src="<?=$killer['user']->getAvatar()?>" alt="<?=$killer['user']->username?>"/>
            </div>
            <div class="top_table_item_wrap">
                <div class="top_table_item_header">
                    <div class="top_table_item_header_name">
                        <?=Yii::t('common', 'КИЛЛЕР')?>
                    </div>
                    <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                        +500 RUB
                    </div>
                </div>
                <div class="top_table_item_body">
                    <div class="top_table_item_body_link">
                        <a target="#" href="/stats/player?steamId=<?=$killer['steam_id']?>&server=<?=$server->tag?>"><?=$killer['user']->username?></a>
                    </div>
                    <div class="top_table_item_body_score">
                        <?=$killer['total_score']?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($scientists)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$scientists['user']->getAvatar()?>" alt="<?=$scientists['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'МИРНЫЙ')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$scientists['steam_id']?>&server=<?=$server->tag?>"><?=$scientists['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$scientists['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($playtime) && !empty($playtime['user'])): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$playtime['user']->getAvatar()?>" alt="<?=$playtime['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ОНЛАЙН')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$playtime['steam_id']?>&server=<?=$server->tag?>"><?=$playtime['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=\common\models\servers\Servers::getPlayTime($playtime['total_score'])?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($hunter)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$hunter['user']->getAvatar()?>" alt="<?=$hunter['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ОХОТНИК')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$hunter['steam_id']?>&server=<?=$server->tag?>"><?=$hunter['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$hunter['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($fermer)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$fermer['user']->getAvatar()?>" alt="<?=$fermer['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ФЕРМЕР')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fermer['steam_id']?>&server=<?=$server->tag?>"><?=$fermer['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$fermer['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($farmer)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$farmer['user']->getAvatar()?>" alt="<?=$farmer['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ФАРМЕР')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$farmer['steam_id']?>&server=<?=$server->tag?>"><?=$farmer['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$farmer['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($fishing)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$fishing['user']->getAvatar()?>" alt="<?=$fishing['user']->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'РЫБАК')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fishing['steam_id']?>&server=<?=$server->tag?>"><?=$fishing['user']->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$fishing['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
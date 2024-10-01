<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var \common\models\user\User $user */
/** @var array $player */
/** @var string $title */

$isCurrentTop = false;
$isCurrent = false;

$currentPosition = null;
if (!empty($player)) {
    foreach ($data['players'] as $i => $model) {
        if ($model['steam_id'] == $player['steam_id']) {
            $currentPosition = $i + 1;
            break;
        }
    }
}
?>

<div class="stats_player_item_wrap <?=$data['attrName']?>">
    <div class="stats_player_item_header"><?=$title?></div>
    <div class="stats_player_item">
        <table class="table">
            <tbody>
            <?php foreach ($data['players'] as $i => $item): ?>
                <?php
                    if (!empty($player)) {
                        $isCurrent = $user->steam_id == $item['steam_id'];
                        if (!$isCurrentTop && $isCurrent) {
                            $isCurrentTop = true;
                        }
                    }
                    if (empty($item['user'])) {
                        continue;
                    }
                    $_user = $item['user'];
                ?>
                <tr class="stats_player_item_player<?= $isCurrent ? ' stats_player_item_player_current' : ''?>">
                    <td class="stats_player_item_player_position<?=$i + 1?>" title="<?=$i + 1?> место"><i class="fas fa-crown"></i></td>
                    <td class="stats_player_item_player_name">
                        <a title="<?=Yii::t('common', 'Открыть статистику игрока')?>" href="/stats/player?steamId=<?=$item['steam_id']?>&server=<?=$server->tag?>">
                            <?=$_user->username?>
                        </a>
                    </td>
                    <td class="stats_player_item_player_score"><?=$item[$data['attrName']]?></td>
                </tr>
                <?php if ($i >= 2) break; ?>
            <?php endforeach; ?>
            </tbody>
            <?php if (!empty($currentPosition)): ?>
            <tfoot>
                <tr class="stats_player_item_player stats_player_item_player_current">
                    <?php if (!$isCurrentTop): ?>
                    <td><?=$currentPosition?></td>
                    <td>
                        <a title="<?=Yii::t('common', 'Открыть статистику игрока')?>" href="/stats/player?steamId=<?=$user->steam_id?>&server=<?=$server->tag?>">
                            <?=$user->username?>
                        </a>
                    </td>
                    <td><?=Statistics::getParam($player, $data['attrName'])?></td>
                    <?php else: ?>
                    <td colspan="3" class="PARAMS_CUR_POS stats_player_item_player_position<?=$currentPosition?>">
                        <?=Yii::t('common', 'Игрок на {PARAMS_CUR_POS} месте', [
                                'PARAMS_CUR_POS' => $currentPosition
                        ])?>
                    </td>
                    <?php endif; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

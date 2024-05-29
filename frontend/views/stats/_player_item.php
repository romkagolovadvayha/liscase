<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */
/** @var string $title */

$isCurrentTop = false;
$isCurrent = false;
?>

<div class="stats_player_item_wrap <?=$data['attrName']?>">
    <div class="stats_player_item_header"><?=$title?></div>
    <div class="stats_player_item">
        <table class="table">
            <tbody>
            <?php foreach ($data['players'] as $i => $item): ?>
                <?php
                    if (!empty($player)) {
                        $isCurrent = $player['steamid'] == $item['steamid'];
                        if (!$isCurrentTop && $isCurrent) {
                            $isCurrentTop = true;
                        }
                    }
                ?>
                <tr class="stats_player_item_player<?= $isCurrent ? ' stats_player_item_player_current' : ''?>">
                    <td class="stats_player_item_player_position<?=$i + 1?>" title="<?=$i + 1?> место"><i class="fas fa-crown"></i></td>
                    <td class="stats_player_item_player_name">
                        <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" href="/stats/player?steamId=<?=$item['steamid']?>&server=<?=$server->tag?>">
                            <?=$item['name']?>
                        </a>
                    </td>
                    <td class="stats_player_item_player_score"><?=$item[$data['attrName']]?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (!empty($data['currentPosition'])): ?>
            <tfoot>
                <tr class="stats_player_item_player stats_player_item_player_current">
                    <?php if (!$isCurrentTop): ?>
                    <td><?=$data['currentPosition']?></td>
                    <td>
                        <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" href="/stats/player?steamId=<?=$player['steamid']?>&server=<?=$server->tag?>">
                            <?=$player['name']?>
                        </a>
                    </td>
                    <td><?=$player[$data['attrName']]?></td>
                    <?php else: ?>
                    <td colspan="3" class="PARAMS_CUR_POS stats_player_item_player_position<?=$data['currentPosition']?>">
                        <?=Yii::t('common', 'Игрок на {PARAMS_CUR_POS} месте', [
                                'PARAMS_CUR_POS' => $data['currentPosition']
                        ])?>
                    </td>
                    <?php endif; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

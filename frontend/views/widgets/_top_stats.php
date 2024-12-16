<?php

/** @var Servers $server */
/** @var UserTop $item */

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserTop;
use yii\helpers\ArrayHelper;

$top = Statistics::getTop($server, null, 1);
?>

<div class="top_table">
    <?php foreach ($top['top'] as $items): ?>
        <?php foreach ($items as $item): ?>
            <div class="top_table_item">
                <div class="top_table_item_image">
                    <img src="<?=$item->user->getAvatar()?>" alt="<?=$item->user->username?>"/>
                </div>
                <div class="top_table_item_wrap">
                    <div class="top_table_item_header">
                        <div class="top_table_item_header_name">
                            <?=ArrayHelper::getValue(UserTop::getRaitingLabel(), $item->key)?>
                        </div>
                        <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                            +500 RUB
                        </div>
                    </div>
                    <div class="top_table_item_body">
                        <div class="top_table_item_body_link">
                            <a target="#" href="/stats/player?steamId=<?=$item->user->steam_id?>&server=<?=$server->tag?>"><?=$item->user->username?></a>
                        </div>
                        <div class="top_table_item_body_score">
                            <?=round($item->value)?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
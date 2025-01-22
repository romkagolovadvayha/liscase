<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

?>

<div class="page-stats__actions-in-game">
    <div class="page-stats__block page-stats__block_with-light text-center">
        <img src="<?=Statistics::getImage($images, 'barrel')?>" alt="" class="mb-32 relative z-1" />
        <p class="p1 text-text-secondary mb-8 text-left relative z-1">
            <?=Yii::t('common', 'Разбито бочек')?>
        </p>
        <h3 class="text-text-main text-left relative z-1"><?=Statistics::getParam($player, 'barrel')?></h3>
    </div>

    <div class="page-stats__block page-stats__block_with-light text-center">
        <img src="<?=Statistics::getImage($images, 'crate_open')?>" alt="" class="mb-32 relative z-1" />
        <p class="p1 text-text-secondary mb-8 text-left relative z-1">
            <?=Yii::t('common', 'Открыто ящиков')?>
        </p>
        <h3 class="text-text-main text-left relative z-1"><?=Statistics::getParam($player, 'crate_open')?></h3>
    </div>

    <div class="page-stats__block page-stats__block_with-light text-center">
        <img src="<?=Statistics::getImage($images, 'parachute')?>" alt="" class="mb-32 relative z-1" />
        <p class="p1 text-text-secondary mb-8 text-left relative z-1">
            <?=Yii::t('common', 'Прыжков с парашюта')?>
        </p>
        <h3 class="text-text-main text-left relative z-1"><?=Statistics::getParam($player, 'parachuteseat')?></h3>
    </div>

    <div class="page-stats__block page-stats__block_with-light text-center">
        <img src="<?=Statistics::getImage($images, 'small-stash')?>" alt="" class="mb-32 relative z-1" />
        <p class="p1 text-text-secondary mb-8 text-left relative z-1">
            <?=Yii::t('common', 'Выкопано тайников')?>
        </p>
        <h3 class="text-text-main text-left relative z-1"><?=Statistics::getParam($player, 'stash')?></h3>
    </div>
</div>
<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$kills = Kills::find()
              ->select(['weapon', 'COUNT(*) as count'])
              ->andWhere(['steam_id' => $steamId])
              ->andWhere(['server_tag' => $server->tag])
              ->andWhere(['wipe' => $server->currentWipe()])
              ->andWhere('weapon IS NOT NULL')
              ->asArray()
              ->groupBy('weapon')
              ->orderBy(['count' => SORT_DESC])
              ->all();

$weapons = [];
foreach ($kills as $item) {
    if (empty($item['weapon'])) {
        continue;
    }
    $weapons[] = [
        'weapon' => $item['weapon'],
        'image' => Statistics::getImage($images, $item['weapon']),
        'name'   => Statistics::getName($names, $item['weapon']),
        'count'  => $item['count'],
    ];
}

if (empty($weapons)) {
    return;
}

$totalWeapons = count($weapons);
$visibleWeapons = array_slice($weapons, 0, 6);
$hiddenWeapons = array_slice($weapons, 6);
$hasMore = count($hiddenWeapons) > 0;
?>
<!-- Орудия убийства -->
<section class="page-stats__block w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Орудия убийства')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'У каждого оружия указано количество убитых')?>"
            ></span>
        </h4>
    </header>

    <div class="page-stats__categories">
        <?php foreach ($visibleWeapons as $index => $weapon): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $weapon['count'] ? $weapon['count'] : 0 ?></span>
                    <img src="<?= $weapon['image'] ?>" alt="<?= $weapon['weapon'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= Yii::t('database', $weapon['name']) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($hasMore): ?>
            <?php foreach ($hiddenWeapons as $index => $weapon): ?>
                <div class="page-stats__category category weapons-item-hidden" style="display: none;">
                    <h5 class="category__count-and-img">
                        <span><?= $weapon['count'] ? $weapon['count'] : 0 ?></span>
                        <img src="<?= $weapon['image'] ?>" alt="<?= $weapon['weapon'] ?>" class="w-64 h-64 object-contain"/>
                    </h5>
                    <p class="category__title"><?= Yii::t('database', $weapon['name']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($hasMore): ?>
        <button type="button" class="weapons-show-more-btn button button-secondary w-full mt-16" data-text-more="<?= Yii::t('common', 'Показать еще {count}') ?>" data-text-less="<?= Yii::t('common', 'Скрыть') ?>">
            <span class="weapons-show-more-text"><?= Yii::t('common', 'Показать еще {count}', ['count' => count($hiddenWeapons)]) ?></span>
            <i class="fas fa-chevron-down"></i>
        </button>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showMoreBtn = document.querySelector('.weapons-show-more-btn');
    if (!showMoreBtn) return;
    
    const hiddenItems = document.querySelectorAll('.weapons-item-hidden');
    if (hiddenItems.length === 0) return;
    
    const textElement = showMoreBtn.querySelector('.weapons-show-more-text');
    const iconElement = showMoreBtn.querySelector('i');
    
    if (!textElement) return;
    
    let isExpanded = false;
    
    showMoreBtn.addEventListener('click', function() {
        isExpanded = !isExpanded;
        
        if (isExpanded) {
            hiddenItems.forEach(item => {
                item.style.display = '';
            });
            textElement.textContent = showMoreBtn.dataset.textLess;
            if (iconElement) {
                iconElement.classList.add('weapons-show-more-icon--rotated');
            }
        } else {
            hiddenItems.forEach(item => {
                item.style.display = 'none';
            });
            textElement.textContent = showMoreBtn.dataset.textMore.replace('{count}', hiddenItems.length);
            if (iconElement) {
                iconElement.classList.remove('weapons-show-more-icon--rotated');
            }
        }
    });
});
</script>
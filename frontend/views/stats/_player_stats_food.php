<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$items = [];
foreach ($player as $item) {
    if (substr($item['key'], 0, 4) !== 'mod_') {
        continue;
    }
    if (strpos($item['key'], 'pie') !== false) {
        continue;
    }
    $key = str_replace('mod_', '', $item['key']);
    if (strpos($key, 'tea') !== false) {
        continue;
    }
    if (strpos($key, 'largemedkit') !== false) {
        continue;
    }
    $items[] = Statistics::getFoodItem($images, $names, $player, $key);
}

usort(
    $items,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);

$totalItems = count($items);
$visibleItems = array_slice($items, 0, 3);
$hiddenItems = array_slice($items, 3);
$hasMore = count($hiddenItems) > 0;

?>
<!-- Любимая еда -->
<section class="page-stats__block w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h3 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Любимая еда')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'В этом блоке отображается все, что сьел игрок за вайп.')?>"
            ></span>
        </h3>
    </header>

    <div class="page-stats__categories">
        <?php foreach ($visibleItems as $item): ?>
            <div class="page-stats__category category <?= $item['key'] ?>">
                <h5 class="category__count-and-img">
                    <span><?= $item['desc'] ?></span>
                    <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= $item['name'] ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($hasMore): ?>
            <?php foreach ($hiddenItems as $item): ?>
                <div class="page-stats__category category food-item-hidden <?= $item['key'] ?>" style="display: none;">
                    <h5 class="category__count-and-img">
                        <span><?= $item['desc'] ?></span>
                        <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                    </h5>
                    <p class="category__title"><?= $item['name'] ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($hasMore): ?>
        <button type="button" class="food-show-more-btn button button-secondary w-full mt-16" data-text-more="<?= Yii::t('common', 'Показать еще {count}') ?>" data-text-less="<?= Yii::t('common', 'Скрыть') ?>">
            <span class="food-show-more-text"><?= Yii::t('common', 'Показать еще {count}', ['count' => count($hiddenItems)]) ?></span>
            <i class="fas fa-chevron-down"></i>
        </button>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showMoreBtn = document.querySelector('.food-show-more-btn');
    if (!showMoreBtn) return;
    
    const hiddenItems = document.querySelectorAll('.food-item-hidden');
    if (hiddenItems.length === 0) return;
    
    const textElement = showMoreBtn.querySelector('.food-show-more-text');
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
                iconElement.classList.add('food-show-more-icon--rotated');
            }
        } else {
            hiddenItems.forEach(item => {
                item.style.display = 'none';
            });
            textElement.textContent = showMoreBtn.dataset.textMore.replace('{count}', hiddenItems.length);
            if (iconElement) {
                iconElement.classList.remove('food-show-more-icon--rotated');
            }
        }
    });
});
</script>
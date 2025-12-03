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

$items = [
    ['key' => 'c4thrown', 'score' => 1],
    ['key' => 'satchelsthrown', 'score' => 0.2],
    ['key' => 'rocket_basic', 'score' => 0.5, 'combined' => ['rocket_basic_rpg']],
    ['key' => 'rocket_hv', 'score' => 0.1, 'combined' => ['rocket_hv_rpg']],
    ['key' => 'rocket_fire', 'score' => 0.1, 'combined' => ['rocket_fire_rpg']],
    ['key' => 'ammo_explosive', 'score' => 0.01],
    ['key' => 'grenade.f1.deployed', 'score' => 0.02],
    ['key' => 'grenade.molotov.deployed', 'score' => 0.05],
    ['key' => 'grenade.beancan.deployed', 'score' => 0.05],
    ['key' => 'grenade.flashbang.deployed', 'score' => 0],
    ['key' => 'grenade.supplysignal.deployed', 'score' => 0],
    ['key' => 'grenade.smoke.deployed', 'score' => 0],
    ['key' => 'grenade.bee.deployed', 'score' => 0],
    ['key' => '40mm_grenade_he', 'score' => 0],
    ['key' => '40mm_grenade_smoke', 'score' => 0],
    ['key' => 'rocket_heatseeker', 'score' => 0],
    ['key' => 'flare.deployed', 'score' => 0],
];

//$keys = [];
//foreach ($items as $item) {
//    $keys[] = str_replace('.deployed', '', $item['key']);
//}

$reider = [];
foreach ($items as $item) {
    $itemData = Statistics::getRaiderItem($names, $images, $player, $item['key'], $item['score']);
    
    // Если есть combined ключи, добавляем их значения к основному
    if (!empty($item['combined']) && is_array($item['combined'])) {
        $combinedCount = $itemData['count'];
        foreach ($item['combined'] as $combinedKey) {
            $combinedCount += Statistics::getParam($player, $combinedKey);
        }
        $itemData['count'] = $combinedCount;
        $itemData['desc'] = $combinedCount;
    }
    
    $reider[] = $itemData;
}

$totalReider = count($reider);
$visibleReider = array_slice($reider, 0, 6);
$hiddenReider = array_slice($reider, 6);
$hasMore = count($hiddenReider) > 0;

?>
<!-- Взрывные устройства -->
<section class="page-stats__block w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h3 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Взрывные устройства')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество использованных взрывчатых предметов игроком.')?>"
            ></span>
        </h3>
    </header>

    <div class="page-stats__categories">
        <?php foreach ($visibleReider as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span>
                        <?= $item['desc'] ?><?php if (!empty($item['score'])): ?><span class="category__x"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="bottom"
                                  data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></span><?php endif; ?>
                    </span>
                    <img src="<?= $item['image'] ?>" alt="" class="w-64 h-64 object-contain" />
                </h5>
                <p class="category__title"><?= Yii::t('database', $item['name']) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($hasMore): ?>
            <?php foreach ($hiddenReider as $item): ?>
                <div class="page-stats__category category reider-item-hidden" style="display: none;">
                    <h5 class="category__count-and-img">
                        <span>
                            <?= $item['desc'] ?><?php if (!empty($item['score'])): ?><span class="category__x"
                                      data-bs-toggle="tooltip"
                                      data-bs-placement="bottom"
                                      data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></span><?php endif; ?>
                        </span>
                        <img src="<?= $item['image'] ?>" alt="" class="w-64 h-64 object-contain" />
                    </h5>
                    <p class="category__title"><?= Yii::t('database', $item['name']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($hasMore): ?>
        <button type="button" class="reider-show-more-btn button button-secondary w-full mt-16" data-text-more="<?= Yii::t('common', 'Показать еще {count}') ?>" data-text-less="<?= Yii::t('common', 'Скрыть') ?>">
            <span class="reider-show-more-text"><?= Yii::t('common', 'Показать еще {count}', ['count' => count($hiddenReider)]) ?></span>
            <i class="fas fa-chevron-down"></i>
        </button>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showMoreBtn = document.querySelector('.reider-show-more-btn');
    if (!showMoreBtn) return;
    
    const hiddenItems = document.querySelectorAll('.reider-item-hidden');
    if (hiddenItems.length === 0) return;
    
    const textElement = showMoreBtn.querySelector('.reider-show-more-text');
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
                iconElement.classList.add('reider-show-more-icon--rotated');
            }
        } else {
            hiddenItems.forEach(item => {
                item.style.display = 'none';
            });
            textElement.textContent = showMoreBtn.dataset.textMore.replace('{count}', hiddenItems.length);
            if (iconElement) {
                iconElement.classList.remove('reider-show-more-icon--rotated');
            }
        }
    });
});
</script>
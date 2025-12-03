<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$items = [
    ['key' => 'gathered_cloth', 'name' => Yii::t('common', 'Ткань'), 'score' => 0.05],
    ['key' => 'gathered_corn', 'name' => Yii::t('common', 'Кукуруза'), 'score' => 0.3],
    ['key' => 'gathered_potato', 'name' => Yii::t('common', 'Картофель'), 'score' => 0.4],
    ['key' => 'gathered_pumpkin', 'name' => Yii::t('common', 'Тыква'), 'score' => 0.5],
    ['key' => 'gathered_blue.berry', 'name' => Yii::t('common', 'Синие ягоды'), 'score' => 0.5],
    ['key' => 'gathered_yellow.berry', 'name' => Yii::t('common', 'Желтые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_red.berry', 'name' => Yii::t('common', 'Красные ягоды'), 'score' => 0.5],
    ['key' => 'gathered_white.berry', 'name' => Yii::t('common', 'Белые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_green.berry', 'name' => Yii::t('common', 'Зеленые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_black.berry', 'name' => Yii::t('common', 'Черные ягоды'), 'score' => 1],
    ['key' => 'gathered_orchid', 'name' => Yii::t('common', 'Орхидея'), 'score' => 0.3],
    ['key' => 'gathered_rose', 'name' => Yii::t('common', 'Розы'), 'score' => 0.3],
    ['key' => 'gathered_sunflower', 'name' => Yii::t('common', 'Подсолнух'), 'score' => 0.3],
    ['key' => 'gathered_wheat', 'name' => Yii::t('common', 'Пшеница'), 'score' => 0.3],
];

$fermers = [];
foreach ($items as $item) {
    $fermers[] = Statistics::getFermItem($images, $player, $item['key'], $item['name'], $item['score']);
}

$totalFermers = count($fermers);
$visibleFermers = array_slice($fermers, 0, 3);
$hiddenFermers = array_slice($fermers, 3);
$hasMore = count($hiddenFermers) > 0;

?> <!-- Фермерство -->
<section class="page-stats__block w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Фермерство')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество выращенных растений игроком.')?>"
            ></span>
        </h4>
    </header>

    <div class="page-stats__categories">
        <?php foreach ($visibleFermers as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $item['desc'] ?><span class="category__x">x<?= $item['score'] ?></span></span>
                    <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= $item['name'] ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($hasMore): ?>
            <?php foreach ($hiddenFermers as $item): ?>
                <div class="page-stats__category category ferm-item-hidden" style="display: none;">
                    <h5 class="category__count-and-img">
                        <span><?= $item['desc'] ?><span class="category__x">x<?= $item['score'] ?></span></span>
                        <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                    </h5>
                    <p class="category__title"><?= $item['name'] ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($hasMore): ?>
        <button type="button" class="ferm-show-more-btn button button-secondary w-full mt-16" data-text-more="<?= Yii::t('common', 'Показать еще {count}') ?>" data-text-less="<?= Yii::t('common', 'Скрыть') ?>">
            <span class="ferm-show-more-text"><?= Yii::t('common', 'Показать еще {count}', ['count' => count($hiddenFermers)]) ?></span>
            <i class="fas fa-chevron-down"></i>
        </button>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showMoreBtn = document.querySelector('.ferm-show-more-btn');
    if (!showMoreBtn) return;
    
    const hiddenItems = document.querySelectorAll('.ferm-item-hidden');
    if (hiddenItems.length === 0) return;
    
    const textElement = showMoreBtn.querySelector('.ferm-show-more-text');
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
                iconElement.classList.add('ferm-show-more-icon--rotated');
            }
        } else {
            hiddenItems.forEach(item => {
                item.style.display = 'none';
            });
            textElement.textContent = showMoreBtn.dataset.textMore.replace('{count}', hiddenItems.length);
            if (iconElement) {
                iconElement.classList.remove('ferm-show-more-icon--rotated');
            }
        }
    });
});
</script>

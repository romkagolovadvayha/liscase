<?php

use common\models\box\DropBlocked;

/** @var \common\models\box\Drop $drop */
/** @var int $serverId */
/** @var \common\models\user\UserDrop $userDrop */

$blockedAt = DropBlocked::getBlocked($drop->id, $serverId);
$blocked = !empty($blockedAt);

$this->registerCss(<<<CSS
.store_launcher_cards_item_wrap {
    position: relative;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.store_launcher_cards_item_wrap.loader {
    opacity: 0.5;
    pointer-events: none;
}

.store_launcher_cards_item_wrap.loader .store_launcher_cards_item::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 3px solid var(--border-color-default);
    border-top-color: var(--primary-colors-main);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

.store_launcher_cards_item {
    position: relative;
    cursor: pointer;
    background: var(--background-teritiary);
    border: 2px solid var(--border-color-default);
    border-radius: var(--card-radius);
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
    aspect-ratio: 1;
}

.store_launcher_cards_item::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, var(--primary-colors-secondary-opacity), transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.store_launcher_cards_item:hover {
    border-color: var(--border-color-hover);
    transform: translateY(-8px) scale(1.05);
    box-shadow: var(--shadow-card), 0 0 30px rgba(255, 97, 52, 0.4);
}

.store_launcher_cards_item:hover::before {
    opacity: 1;
    animation: rotate 10s linear infinite;
}

.store_launcher_cards_item:active {
    transform: translateY(-4px) scale(1.02);
}

.store_launcher_cards_item_image {
    position: relative;
    z-index: 1;
    width: 100%;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.store_launcher_cards_item_image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.5));
    transition: transform 0.3s ease;
}

.store_launcher_cards_item:hover .store_launcher_cards_item_image img {
    transform: scale(1.1) rotate(5deg);
}

.store_launcher_cards_item_count {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--base-linear-gradiend);
    color: var(--text-main);
    font-family: var(--font-main);
    font-size: 14px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: var(--button-radius);
    z-index: 2;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

.store_launcher_cards_item_button {
    position: relative;
    z-index: 1;
    width: 100%;
    background: var(--base-linear-gradiend);
    color: var(--icon-in-button);
    font-family: var(--font-main);
    font-size: 16px;
    font-weight: 600;
    text-align: center;
    padding: 12px;
    border-radius: var(--button-main-radius);
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(235, 12, 53, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.store_launcher_cards_item:hover .store_launcher_cards_item_button {
    background: var(--button-primary-image-hover);
    box-shadow: 0 6px 20px rgba(235, 12, 53, 0.5);
    transform: translateY(-2px);
}

.store_launcher_cards_item_button.blocked {
    background: var(--background-secondary);
    color: var(--text-secondary);
    box-shadow: none;
    cursor: not-allowed;
    border: 2px solid var(--border-color-default);
}

.store_launcher_cards_item_button.blocked:hover {
    transform: none;
    box-shadow: none;
}

.store_launcher_cards_item_blocked_wrap {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(8, 2, 36, 0.95);
    border-radius: var(--card-radius);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px;
    z-index: 3;
    backdrop-filter: blur(4px);
}

.store_launcher_cards_item_blocked_title {
    font-family: var(--font-main);
    font-size: 16px;
    font-weight: 700;
    color: var(--color-rules-punishment);
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.store_launcher_cards_item_blocked_timer {
    font-family: var(--font-main);
    font-size: 12px;
    color: var(--text-teritiary);
    text-align: center;
    padding: 6px 12px;
    background: var(--background-secondary);
    border-radius: var(--button-radius);
    border: 1px solid var(--border-color-default);
}

@media (max-width: 768px) {
    .store_launcher_cards_item {
        padding: 12px;
    }
    
    .store_launcher_cards_item_button {
        font-size: 12px;
        padding: 8px;
    }
    
    .store_launcher_cards_item_count {
        font-size: 12px;
        padding: 3px 8px;
        top: 6px;
        right: 6px;
    }
}
CSS
);
?>
<div class="store_launcher_cards_item_wrap" data-category-id="<?=$drop->category_id?>" data-title="<?=Yii::t('database', $drop->name)?>">
    <div class="store_launcher_cards_item" data-id="<?=$userDrop->id?>">
        <div class="store_launcher_cards_item_image">
            <img src="<?= $drop->image100() ?>" alt="<?=Yii::t('database', $drop->name)?>" loading="lazy">
        </div>
        <?php if ($userDrop->count > 1): ?>
            <div class="store_launcher_cards_item_count">
                x<?= $userDrop->count ?>
            </div>
        <?php endif; ?>
        <div class="store_launcher_cards_item_button<?=$blocked ? ' blocked' : ''?>">
            <?php if ($blocked): ?>
                🔒 <?=Yii::t('common', 'Недоступно')?>
            <?php else: ?>
                ✓ <?=Yii::t('common', 'Получить')?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($blocked): ?>
        <div class="store_launcher_cards_item_blocked_wrap">
            <div class="store_launcher_cards_item_blocked_title">🔒 <?=Yii::t('common', 'Вайп блок')?></div>
            <div class="store_launcher_cards_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($blockedAt)?>"><?=$blockedAt?></div>
        </div>
    <?php endif; ?>
</div>
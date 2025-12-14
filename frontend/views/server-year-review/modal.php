<?php

/** @var yii\web\View $this */
/** @var \common\models\servers\Servers $server */
/** @var array $metrics */
/** @var \common\models\servers\Servers[] $allServers */
/** @var int|null $currentUserId */
/** @var array $images */
/** @var array $names */

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\statistics\Statistics;

// Группируем метрики по категориям с единицами измерения, ключами для картинок и иконками Font Awesome
$metricGroups = [
    'combat' => [
        'label' => Yii::t('common', 'Боевые метрики'),
        'metrics' => [
            'top_killers' => ['label' => Yii::t('common', 'Топ убийц'), 'unit' => 'убийств', 'image_key' => 'kills', 'icon' => 'fa-crosshairs'],
            'top_deaths' => ['label' => Yii::t('common', 'Топ смертей'), 'unit' => 'смертей', 'image_key' => 'deaths', 'icon' => 'fa-skull'],
            'top_scientists' => ['label' => Yii::t('common', 'Убито ученых'), 'unit' => 'чел.', 'image_key' => 'scientists', 'icon' => 'fa-user-ninja'],
        ]
    ],
    'resources' => [
        'label' => Yii::t('common', 'Ресурсы'),
        'metrics' => [
            'top_sulfur' => ['label' => Yii::t('common', 'Добыча серы'), 'unit' => 'ед.', 'image_key' => 'sulfur.ore', 'icon' => 'fa-mountain'],
            'top_wood' => ['label' => Yii::t('common', 'Добыча дерева'), 'unit' => 'ед.', 'image_key' => 'wood', 'icon' => 'fa-tree'],
            'top_metal' => ['label' => Yii::t('common', 'Добыча металла'), 'unit' => 'ед.', 'image_key' => 'metal.ore', 'icon' => 'fa-cog'],
            'top_stone' => ['label' => Yii::t('common', 'Добыча камня'), 'unit' => 'ед.', 'image_key' => 'stones', 'icon' => 'fa-gem'],
            'top_scrap' => ['label' => Yii::t('common', 'Добыто скрапа'), 'unit' => 'ед.', 'image_key' => 'scrap', 'icon' => 'fa-coins'],
            'top_fat' => ['label' => Yii::t('common', 'Добыто животного жира'), 'unit' => 'ед.', 'image_key' => 'fat.animal', 'icon' => 'fa-tint'],
        ]
    ],
    'explosives' => [
        'label' => Yii::t('common', 'Взрывчатка'),
        'metrics' => [
            'top_rockets' => ['label' => Yii::t('common', 'Взорвано ракет'), 'unit' => 'ракет', 'image_key' => 'rocket_basic', 'icon' => 'fa-rocket'],
            'top_c4' => ['label' => Yii::t('common', 'Взорвано C4'), 'unit' => 'шт.', 'image_key' => 'c4thrown', 'icon' => 'fa-bomb'],
            'top_satchels' => ['label' => Yii::t('common', 'Взорвано сатчелей'), 'unit' => 'шт.', 'image_key' => 'satchelsthrown', 'icon' => 'fa-fire'],
        ]
    ],
    'activities' => [
        'label' => Yii::t('common', 'Активности'),
        'metrics' => [
            'top_playtime' => ['label' => Yii::t('common', 'Время на сервере'), 'unit' => 'минут', 'image_key' => 'playtime', 'icon' => 'fa-clock'],
            'top_wipes' => ['label' => Yii::t('common', 'Проведено вайпов'), 'unit' => 'вайпов', 'image_key' => null, 'icon' => 'fa-calendar-alt'],
            'top_boxes' => ['label' => Yii::t('common', 'Открыто ящиков'), 'unit' => 'шт.', 'image_key' => 'crate_open', 'icon' => 'fa-box'],
            'top_barrels' => ['label' => Yii::t('common', 'Разбито бочек'), 'unit' => 'бочек', 'image_key' => 'barrel', 'icon' => 'fa-drum'],
            'top_cupboard_raids' => ['label' => Yii::t('common', 'Рейдов шкафов'), 'unit' => 'рейдов', 'image_key' => null, 'icon' => 'fa-door-open'],
        ]
    ],
    'collecting' => [
        'label' => Yii::t('common', 'Собирательство'),
        'metrics' => [
            'top_animals' => ['label' => Yii::t('common', 'Убито животных'), 'unit' => 'животных', 'image_key' => 'animals', 'icon' => 'fa-paw'],
            'top_fish' => ['label' => Yii::t('common', 'Поймано рыбы'), 'unit' => 'рыб', 'image_key' => 'fish', 'icon' => 'fa-fish'],
            'top_berries' => ['label' => Yii::t('common', 'Собрано ягод'), 'unit' => 'шт.', 'image_key' => 'blue_berry', 'icon' => 'fa-apple-alt'],
            'top_cloth' => ['label' => Yii::t('common', 'Собрано ткани'), 'unit' => 'ед.', 'image_key' => 'gathered_cloth', 'icon' => 'fa-tshirt'],
        ]
    ],
    'cards' => [
        'label' => Yii::t('common', 'Карты'),
        'metrics' => [
            'top_red_cards' => ['label' => Yii::t('common', 'Красные карты'), 'unit' => 'карт', 'image_key' => 'card_level_3', 'icon' => 'fa-id-card'],
            'top_green_cards' => ['label' => Yii::t('common', 'Зеленые карты'), 'unit' => 'карт', 'image_key' => 'card_level_2', 'icon' => 'fa-id-card'],
            'top_blue_cards' => ['label' => Yii::t('common', 'Синие карты'), 'unit' => 'карт', 'image_key' => 'card_level_1', 'icon' => 'fa-id-card'],
        ]
    ],
    'reports' => [
        'label' => Yii::t('common', 'Репорты'),
        'metrics' => [
            'top_reporters' => ['label' => Yii::t('common', 'Отправлено репортов'), 'unit' => 'репортов', 'image_key' => null, 'icon' => 'fa-flag'],
            'top_reported' => ['label' => Yii::t('common', 'Получено репортов'), 'unit' => 'репортов', 'image_key' => null, 'icon' => 'fa-exclamation-triangle'],
        ]
    ],
    'creative' => [
        'label' => Yii::t('common', 'Творчество'),
        'metrics' => [
            'top_kill_distance' => ['label' => Yii::t('common', 'Максимальная дистанция убийства'), 'unit' => 'м', 'image_key' => 'kills', 'icon' => 'fa-bullseye'],
            'top_signs' => ['label' => Yii::t('common', 'Создано табличек'), 'unit' => 'табличек', 'image_key' => null, 'icon' => 'fa-sign'],
        ]
    ],
];

// Функция для форматирования чисел
$formatNumber = function($number) {
    return number_format($number, 0, '', ' ');
};

?>

<div class="year-review-modal" id="year-review-modal">
    <div class="year-review-modal__overlay"></div>
    <div class="year-review-modal__content">
        <button type="button" class="year-review-modal__close" aria-label="<?= Yii::t('common', 'Закрыть') ?>">
            ✕
        </button>
        
        <div class="year-review-modal__header">
            <h1 class="year-review-modal__title"><?= Yii::t('common', 'ИТОГИ ГОДА') ?></h1>
            <div class="year-review-modal__server"><?= Html::encode(Yii::t('database', $server->name)) ?></div>
        </div>

        <div class="year-review-modal__body">
            <?php 
            $screenIndex = 0;
            foreach ($metricGroups as $groupKey => $group): 
                $screenMetrics = [];
                foreach ($group['metrics'] as $metricKey => $metricConfig) {
                    if (!empty($metrics[$metricKey])) {
                        $screenMetrics[$metricKey] = [
                            'label' => $metricConfig,
                            'data' => $metrics[$metricKey]
                        ];
                    }
                }
                
                if (empty($screenMetrics)) {
                    continue;
                }
            ?>
                <div class="year-review-screen" data-screen="<?= $screenIndex ?>" <?= $screenIndex > 0 ? 'style="display: none;"' : '' ?>>
                    <div class="year-review-screen__title"><?= Html::encode($group['label']) ?></div>
                    
                    <div class="year-review-metrics">
                        <?php foreach ($screenMetrics as $metricKey => $metric): ?>
                            <?php
                            $metricLabel = is_array($metric['label']) ? $metric['label']['label'] : $metric['label'];
                            $metricUnit = is_array($metric['label']) && isset($metric['label']['unit']) ? $metric['label']['unit'] : '';
                            $imageKey = is_array($metric['label']) && isset($metric['label']['image_key']) ? $metric['label']['image_key'] : null;
                            $iconClass = is_array($metric['label']) && isset($metric['label']['icon']) ? $metric['label']['icon'] : 'fa-chart-bar';
                            
                            // Получаем картинку для метрики
                            $metricImage = null;
                            $hasImage = false;
                            if ($imageKey) {
                                $metricImage = Statistics::getImage($images, $imageKey);
                                // Если вернулась дефолтная картинка, значит картинки нет
                                if ($metricImage !== '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png') {
                                    $hasImage = true;
                                }
                            }
                            ?>
                            <div class="year-review-metric">
                                <div class="year-review-metric__title">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= Html::encode($metricImage) ?>" alt="" class="year-review-metric__icon" />
                                    <?php else: ?>
                                        <i class="fas <?= Html::encode($iconClass) ?> year-review-metric__icon year-review-metric__icon--fa"></i>
                                    <?php endif; ?>
                                    <span><?= Html::encode($metricLabel) ?></span>
                                </div>
                                <div class="year-review-metric__list">
                                    <?php foreach ($metric['data'] as $index => $item): ?>
                                        <?php
                                        $medal = '';
                                        $medalClass = '';
                                        if ($index === 0) {
                                            $medal = '🥇';
                                            $medalClass = 'gold';
                                        } elseif ($index === 1) {
                                            $medal = '🥈';
                                            $medalClass = 'silver';
                                        } elseif ($index === 2) {
                                            $medal = '🥉';
                                            $medalClass = 'bronze';
                                        }
                                        ?>
                                        <div class="year-review-metric__item year-review-metric__item--<?= $medalClass ?>">
                                            <div class="year-review-metric__left">
                                                <div class="year-review-metric__position"><?= $medal ?></div>
                                                <div class="year-review-metric__avatar">
                                                    <img src="<?= Html::encode($item['avatar'] ?? '') ?>" alt="<?= Html::encode($item['username']) ?>" />
                                                </div>
                                            </div>
                                            <div class="year-review-metric__right">
                                                <div class="year-review-metric__name"><?= Html::encode($item['username']) ?></div>
                                                <div class="year-review-metric__value"><?= $formatNumber($item['value']) ?><?= $metricUnit ? ' ' . Html::encode($metricUnit) : '' ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php 
                $screenIndex++;
            endforeach; ?>
            
            <?php if (isset($metrics['total_bans']) && $metrics['total_bans'] > 0): ?>
                <div class="year-review-screen" data-screen="<?= $screenIndex ?>" style="display: none;">
                    <div class="year-review-screen__title"><?= Yii::t('common', 'Статистика банов') ?></div>
                    <div class="year-review-total">
                        <div class="year-review-total__label"><?= Yii::t('common', 'Выдано банов всего') ?></div>
                        <div class="year-review-total__value"><?= $formatNumber($metrics['total_bans']) ?></div>
                    </div>
                </div>
                <?php $screenIndex++; ?>
            <?php endif; ?>
            
            <!-- Экран с кнопкой "Мои итоги" и выбором серверов -->
            <div class="year-review-screen" data-screen="<?= $screenIndex ?>" style="display: none;">
                <div class="year-review-screen__title"><?= Yii::t('common', 'Дополнительно') ?></div>
                
                <div class="year-review-actions">
                    <?php if ($currentUserId): ?>
                        <div class="year-review-action">
                            <a 
                                href="<?= Url::to(['/year-review/generate', 'userId' => $currentUserId]) ?>" 
                                target="_blank"
                                class="year-review-action__button year-review-action__button--primary"
                            >
                                <?= Yii::t('common', 'Мои итоги за год') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($allServers) && count($allServers) > 1): ?>
                        <div class="year-review-action">
                            <div class="year-review-action__label"><?= Yii::t('common', 'Итоги других серверов') ?></div>
                            <div class="year-review-servers">
                                <?php foreach ($allServers as $srv): ?>
                                    <?php if ($srv->id == $server->id) continue; ?>
                                    <button 
                                        type="button"
                                        class="year-review-server-btn"
                                        data-server-id="<?= $srv->id ?>"
                                    >
                                        <?= Html::encode(Yii::t('database', $srv->name)) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="year-review-modal__footer">
            <button type="button" class="year-review-nav year-review-nav--prev" disabled>
                ← <?= Yii::t('common', 'Назад') ?>
            </button>
            <div class="year-review-nav__indicators">
                <?php 
                $totalScreens = $screenIndex + 1; // +1 для экрана с кнопками
                for ($i = 0; $i < $totalScreens; $i++): 
                ?>
                    <span class="year-review-nav__indicator <?= $i === 0 ? 'active' : '' ?>" data-screen="<?= $i ?>"></span>
                <?php endfor; ?>
            </div>
            <button type="button" class="year-review-nav year-review-nav--next">
                <?= Yii::t('common', 'Далее') ?> →
            </button>
        </div>
    </div>
</div>


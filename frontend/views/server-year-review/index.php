<?php

/** @var yii\web\View $this */
/** @var \common\models\servers\Servers $server */
/** @var array $metrics */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Итоги года - {server}', ['server' => Yii::t('database', $server->name)]);

// Регистрация ассетов
\frontend\assets\MainAsset::register($this);
?>

<div class="container mt-4 server-metrics-page">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><?= Yii::t('common', 'Итоги года - {server}', ['server' => Html::encode(Yii::t('database', $server->name))]) ?></h1>
            
            <?php if (empty($metrics)): ?>
                <div class="server-metrics-empty">
                    <?= Yii::t('common', 'Метрики для этого сервера пока не доступны') ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php
                    // Функция для форматирования чисел
                    $formatNumber = function($number) {
                        return number_format($number, 0, '', ' ');
                    };

                    // Функция для отображения топ-3
                    $renderTop3 = function($topData, $label, $unit = '') use ($formatNumber) {
                        if (empty($topData)) {
                            return '';
                        }
                        
                        $html = '<div class="col-md-6 col-lg-4 mb-4">';
                        $html .= '<div class="server-metrics-card">';
                        $html .= '<div class="server-metrics-card__header">';
                        $html .= '<h5>' . Html::encode($label) . '</h5>';
                        $html .= '</div>';
                        $html .= '<div class="server-metrics-card__body">';
                        $html .= '<ol>';
                        
                        foreach ($topData as $index => $item) {
                            $medal = '';
                            if ($index === 0) $medal = '🥇';
                            elseif ($index === 1) $medal = '🥈';
                            elseif ($index === 2) $medal = '🥉';
                            
                            $value = $formatNumber($item['value']) . ($unit ? ' ' . $unit : '');
                            $html .= '<li>';
                            $html .= '<strong>' . $medal . ' ' . Html::encode($item['username']) . '</strong>';
                            $html .= '<span class="value">' . $value . '</span>';
                            $html .= '</li>';
                        }
                        
                        $html .= '</ol>';
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                        
                        return $html;
                    };

                    // Отображение всех метрик
                    echo $renderTop3($metrics['top_killers'] ?? [], Yii::t('common', 'Топ убийц'), 'убийств');
                    echo $renderTop3($metrics['top_deaths'] ?? [], Yii::t('common', 'Топ смертей'), 'смертей');
                    echo $renderTop3($metrics['top_playtime'] ?? [], Yii::t('common', 'Время на сервере'), 'минут');
                    echo $renderTop3($metrics['top_sulfur'] ?? [], Yii::t('common', 'Добыча серы'), 'ед.');
                    echo $renderTop3($metrics['top_wood'] ?? [], Yii::t('common', 'Добыча дерева'), 'ед.');
                    echo $renderTop3($metrics['top_metal'] ?? [], Yii::t('common', 'Добыча металла'), 'ед.');
                    echo $renderTop3($metrics['top_stone'] ?? [], Yii::t('common', 'Добыча камня'), 'ед.');
                    echo $renderTop3($metrics['top_rockets'] ?? [], Yii::t('common', 'Взорвано ракет'), 'ракет');
                    echo $renderTop3($metrics['top_c4'] ?? [], Yii::t('common', 'Взорвано C4'), 'шт.');
                    echo $renderTop3($metrics['top_satchels'] ?? [], Yii::t('common', 'Взорвано сатчелей'), 'шт.');
                    echo $renderTop3($metrics['top_scientists'] ?? [], Yii::t('common', 'Убито ученых'), 'чел.');
                    echo $renderTop3($metrics['top_animals'] ?? [], Yii::t('common', 'Убито животных'), 'животных');
                    echo $renderTop3($metrics['top_fish'] ?? [], Yii::t('common', 'Поймано рыбы'), 'рыб');
                    echo $renderTop3($metrics['top_berries'] ?? [], Yii::t('common', 'Собрано ягод'), 'шт.');
                    echo $renderTop3($metrics['top_boxes'] ?? [], Yii::t('common', 'Открыто ящиков'), 'шт.');
                    echo $renderTop3($metrics['top_barrels'] ?? [], Yii::t('common', 'Разбито бочек'), 'бочек');
                    echo $renderTop3($metrics['top_cupboard_raids'] ?? [], Yii::t('common', 'Рейдов шкафов'), 'рейдов');
                    echo $renderTop3($metrics['top_kill_distance'] ?? [], Yii::t('common', 'Максимальная дистанция убийства'), 'м');
                    echo $renderTop3($metrics['top_reporters'] ?? [], Yii::t('common', 'Отправлено репортов'), 'репортов');
                    echo $renderTop3($metrics['top_reported'] ?? [], Yii::t('common', 'Получено репортов'), 'репортов');
                    echo $renderTop3($metrics['top_wipes'] ?? [], Yii::t('common', 'Проведено вайпов'), 'вайпов');
                    echo $renderTop3($metrics['top_red_cards'] ?? [], Yii::t('common', 'Использовано красных карт'), 'карт');
                    echo $renderTop3($metrics['top_green_cards'] ?? [], Yii::t('common', 'Использовано зеленых карт'), 'карт');
                    echo $renderTop3($metrics['top_blue_cards'] ?? [], Yii::t('common', 'Использовано синих карт'), 'карт');
                    echo $renderTop3($metrics['top_signs'] ?? [], Yii::t('common', 'Создано табличек'), 'табличек');
                    ?>
                </div>
                
                <?php if (isset($metrics['total_bans']) && $metrics['total_bans'] > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="server-metrics-card">
                                <div class="server-metrics-card__header">
                                    <h5><?= Yii::t('common', 'Выдано банов всего') ?></h5>
                                </div>
                                <div class="server-metrics-card__body">
                                    <div class="text-center" style="font-size: 32px; font-weight: 600; color: var(--primary-colors-main);">
                                        <?= number_format($metrics['total_bans'], 0, '', ' ') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


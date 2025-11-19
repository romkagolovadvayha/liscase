<?php

use yii\helpers\Html;
use common\helpers\MapLocalization;

/** @var \common\models\map\MapList $map */
/** @var array $detail */
/** @var \common\models\servers\Servers $server */
/** @var int|null $userVotedMapId */
/** @var array $userVotedMapIds */
/** @var array $biomeLabels */
/** @var \common\models\map\MapList|null $prevMap */
/** @var \common\models\map\MapList|null $nextMap */
/** @var bool $isFixed */

$isVoted = !empty($userVotedMapIds) && in_array($detail['id'], $userVotedMapIds);

?>
<section class="mapsV2__detail"
         data-role="map-detail"
         data-map-detail-id="<?= $detail['id'] ?>"
         data-map-size="<?= $detail['size'] ?? '' ?>"
         data-map-server-id="<?= $server->id ?>"
         data-map-monuments='<?= Html::encode(json_encode($detail['monuments'] ?? [])) ?>'>
    <div class="mapsV2__detail-visual">
        <?php
        $imageSrc = $detail['imageIconUrl'] ?? $detail['rawImageUrl'] ?? $detail['image'] ?? $detail['imagePreview'] ?? '';
        $previewSrc = $detail['imagePreview'] ?? $detail['image'] ?? '';
        $hasFullImage = !empty($detail['imageIconUrl']) || !empty($detail['rawImageUrl']) || !empty($detail['image']);
        ?>
        <div class="mapsV2__preview<?= $hasFullImage ? ' is-clickable' : '' ?>"
             data-role="preview"
             data-src="<?= Html::encode($imageSrc) ?>">
            <img src="<?= Html::encode($previewSrc) ?>" alt="<?= Html::encode($detail['hash'] ?? '') ?>" data-role="preview-image">
            <div class="mapsV2__markers" data-role="markers" data-debug-size="<?= (int)($detail['size'] ?? 0) ?>" data-debug-monuments-count="<?= count($detail['monuments'] ?? []) ?>">
                <?php
                $mapSize = (int)($detail['size'] ?? 0);
                $monumentsData = $detail['monuments'] ?? [];
                
                // Временный отладочный вывод (также в data-атрибуты для JS)
                echo "<!-- DEBUG: mapSize=" . $mapSize . ", monumentsCount=" . count($monumentsData);
                if (!empty($monumentsData)) {
                    $firstMon = $monumentsData[0] ?? null;
                    if ($firstMon) {
                        echo ", firstMonumentType=" . htmlspecialchars($firstMon['type'] ?? 'N/A');
                        echo ", firstMonumentCoords=" . htmlspecialchars(json_encode($firstMon['coordinates'] ?? null));
                    }
                } else {
                    echo ", monumentsDataIsEmpty=true";
                    echo ", detailKeys=" . htmlspecialchars(implode(',', array_keys($detail ?? [])));
                }
                echo " -->";
                
                if ($mapSize > 0 && !empty($monumentsData)) {
                    $halfSize = $mapSize / 2;
                    
                    // Фильтруем монументы: исключаем те, что содержат определенные слова
                    $excludedWords = ['подстанция', 'Наковальня', 'ЛЭП', 'камень', 'глыба', 'скала', 'скважина', 'ice', 'вход', 'Cave', 'powerline', 'ruin', 'руины'];
                    $filteredMonuments = array_filter($monumentsData, function($monument) use ($excludedWords) {
                        $label = mb_strtolower($monument['label'] ?? '', 'UTF-8');
                        $type = mb_strtolower($monument['type'] ?? '', 'UTF-8');
                        foreach ($excludedWords as $word) {
                            $wordLower = mb_strtolower($word, 'UTF-8');
                            if (mb_strpos($label, $wordLower) !== false || mb_strpos($type, $wordLower) !== false) {
                                return false;
                            }
                        }
                        return true;
                    });
                    
                    // Рендерим маркеры только для первых 200 отфильтрованных монументов
                    $monumentsToShow = array_slice($filteredMonuments, 0, 200);
                    
                    foreach ($monumentsToShow as $index => $monument) {
                        $coordinates = $monument['coordinates'] ?? null;
                        if (!$coordinates || !is_array($coordinates)) {
                            continue;
                        }
                        
                        $x = $coordinates['x'] ?? $coordinates['X'] ?? null;
                        $y = $coordinates['y'] ?? $coordinates['Y'] ?? null;
                        
                        if ($x === null || $y === null || !is_numeric($x) || !is_numeric($y)) {
                            continue;
                        }
                        
                        // Конвертируем координаты из игровых в проценты
                        $posX = (($x + $halfSize) / $mapSize) * 100;
                        $posY = 100 - ((($y + $halfSize) / $mapSize) * 100);
                        
                        // Ограничиваем значения от 0 до 100
                        $posX = max(0, min(100, $posX));
                        $posY = max(0, min(100, $posY));
                        
                        $monumentName = $monument['label'] ?? $monument['type'] ?? '';
                        $monumentLabel = Html::encode(MapLocalization::monument($monumentName, 'ru-RU'));
                        ?>
                        <div class="mapsV2__marker"
                             data-monument-index="<?= $index ?>"
                             style="left: <?= $posX ?>%; top: <?= $posY ?>%;"
                             title="<?= $monumentLabel ?>">
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="mapsV2__navigation">
            <?php if ($prevMap): ?>
                <a href="/maps-v2/detail/<?= $prevMap->id ?>?server_id=<?= $server->id ?>"
                   class="mapsV2__nav mapsV2__nav--prev show-modal-link"
                   data-href="/maps-v2/detail/<?= $prevMap->id ?>?server_id=<?= $server->id ?>"
                   data-target="modal-dialog"
                   data-size="modal-xxl"
                   data-content-overflow="unset"
                   aria-label="<?= Yii::t('common', 'Предыдущая карта') ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <button type="button" class="mapsV2__nav mapsV2__nav--prev" disabled aria-label="<?= Yii::t('common', 'Предыдущая карта') ?>">
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            
            <?php if ($nextMap): ?>
                <a href="/maps-v2/detail/<?= $nextMap->id ?>?server_id=<?= $server->id ?>"
                   class="mapsV2__nav mapsV2__nav--next show-modal-link"
                   data-href="/maps-v2/detail/<?= $nextMap->id ?>?server_id=<?= $server->id ?>"
                   data-target="modal-dialog"
                   data-size="modal-xxl"
                   data-content-overflow="unset"
                   aria-label="<?= Yii::t('common', 'Следующая карта') ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <button type="button" class="mapsV2__nav mapsV2__nav--next" disabled aria-label="<?= Yii::t('common', 'Следующая карта') ?>">
                    <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
        
        <div class="mapsV2__detail-links">
            <?php if (!empty($detail['rustMapsUrl'])): ?>
                <a class="mapsV2__rustmaps-button"
                   href="<?= Html::encode($detail['rustMapsUrl']) ?>"
                   target="_blank"
                   rel="nofollow noopener">
                    <i class="fas fa-external-link-alt"></i>
                    <?= Yii::t('common', 'RustMaps') ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($detail['downloadUrl'])): ?>
                <a class="mapsV2__download-button"
                   href="<?= Html::encode($detail['downloadUrl']) ?>"
                   target="_blank"
                   rel="nofollow noopener">
                    <i class="fas fa-download"></i>
                    <?= Yii::t('common', 'Скачать карту') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mapsV2__detail-body">
        <header class="mapsV2__detail-header">
            <p class="mapsV2__detail-type" data-role="detail-type"><?= Html::encode($detail['type'] ?? 'Procedural') ?></p>
        </header>

        <div class="mapsV2__stats-grid">
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Размер') ?></span>
                <span class="mapsV2__stat-value" data-stat="size"><?= $detail['size'] ?? '' ?> x <?= $detail['size'] ?? '' ?></span>
            </div>
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Seed') ?></span>
                <span class="mapsV2__stat-value" data-stat="seed"><?= Html::encode($detail['seed'] ?? '') ?></span>
            </div>
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Сохранённая версия') ?></span>
                <span class="mapsV2__stat-value" data-stat="saveVersion"><?= Html::encode($detail['saveVersion'] ?? '–') ?></span>
            </div>
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Голоса') ?></span>
                <span class="mapsV2__stat-value" data-role="votes-count"><?= $detail['voteCount'] ?? 0 ?></span>
            </div>
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Земли на карте') ?></span>
                <span class="mapsV2__stat-value" data-stat="landPercentage"><?= ($detail['landPercentage'] ?? null) !== null ? ($detail['landPercentage'] . '%') : '–' ?></span>
            </div>
            <div class="mapsV2__stat">
                <span class="mapsV2__stat-label"><?= Yii::t('common', 'Монументов') ?></span>
                <span class="mapsV2__stat-value" data-stat="monuments"><?= $detail['totalMonuments'] ?? (isset($detail['monuments']) ? count($detail['monuments']) : 0) ?></span>
            </div>
        </div>

        <?php if (!empty($detail['biomePercentages'])): ?>
            <div class="mapsV2__biomes" data-role="biomes">
                <h3><?= Yii::t('common', 'Биомы') ?></h3>
                <div class="mapsV2__biomes-list" data-role="biomes-list">
                    <?php foreach ($detail['biomePercentages'] as $code => $value): ?>
                        <div class="mapsV2__biome">
                            <span class="mapsV2__biome-label"><?= Html::encode($biomeLabels[$code] ?? strtoupper($code)) ?></span>
                            <span class="mapsV2__biome-value"><?= floor($value * 10) / 10 ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($detail['monuments'])): ?>
            <?php
            // Фильтруем монументы: исключаем те, что содержат определенные слова
            $excludedWords = ['подстанция', 'Наковальня', 'ЛЭП', 'камень', 'глыба', 'скала', 'скважина', 'ice', 'вход', 'Cave', 'powerline', 'ruin', 'руины'];
            $filteredMonuments = array_filter($detail['monuments'], function($monument) use ($excludedWords) {
                $label = mb_strtolower($monument['label'] ?? '', 'UTF-8');
                $type = mb_strtolower($monument['type'] ?? '', 'UTF-8');
                foreach ($excludedWords as $word) {
                    $wordLower = mb_strtolower($word, 'UTF-8');
                    if (mb_strpos($label, $wordLower) !== false || mb_strpos($type, $wordLower) !== false) {
                        return false;
                    }
                }
                return true;
            });
            ?>
            <div class="mapsV2__monuments" data-role="monuments">
                <h3><?= Yii::t('common', 'Монументы') ?></h3>
                <div class="mapsV2__monuments-list" data-role="monuments-list">
                    <?php foreach (array_slice($filteredMonuments, 0, 200) as $index => $monument): ?>
                        <?php
                        $monumentName = $monument['label'] ?? $monument['type'] ?? '';
                        $monumentTranslated = MapLocalization::monument($monumentName, 'ru-RU');
                        ?>
                        <span class="mapsV2__monument-chip"
                              title="<?= Html::encode($monumentTranslated) ?>"
                              data-monument-index="<?= $index ?>">
                            <?= Html::encode($monumentTranslated) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isFixed): ?>
            <?= $this->render('_voters', [
                'voters' => $detail['voters'] ?? [],
                'mapId' => $detail['id'],
                'serverId' => $server->id,
                'isVoted' => $isVoted,
            ]) ?>
        <?php endif; ?>
    </div>
</section>


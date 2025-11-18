<?php

use yii\helpers\Html;

/** @var \common\models\map\MapList $map */
/** @var array $detail */
/** @var \common\models\servers\Servers $server */
/** @var int|null $userVotedMapId */
/** @var array $userVotedMapIds */
/** @var array $biomeLabels */

$isVoted = !empty($userVotedMapIds) && in_array($detail['id'], $userVotedMapIds);

?>
<section class="mapsV2__detail">
    <div class="mapsV2__detail-visual">
        <?php
        $imageSrc = $detail['imageIconUrl'] ?? $detail['rawImageUrl'] ?? $detail['image'] ?? $detail['imagePreview'] ?? '';
        $previewSrc = $detail['imagePreview'] ?? $detail['image'] ?? '';
        $isClickable = !empty($detail['imageIconUrl']) || !empty($detail['rawImageUrl']) || !empty($detail['image']);
        ?>
        <div class="mapsV2__preview<?= $isClickable ? ' is-clickable' : '' ?>"
             data-role="preview"
             data-src="<?= Html::encode($imageSrc) ?>">
            <img src="<?= Html::encode($previewSrc) ?>" alt="<?= Html::encode($detail['hash'] ?? '') ?>" data-role="preview-image">
            <div class="mapsV2__markers" data-role="markers"></div>
        </div>
    </div>

    <div class="mapsV2__detail-body" data-map-detail-id="<?= $detail['id'] ?>">
        <header class="mapsV2__detail-header">
            <div>
                <p class="mapsV2__detail-type" data-role="detail-type"><?= Html::encode($detail['type'] ?? 'Procedural') ?></p>
                <h2 class="mapsV2__detail-title" data-role="detail-title"><?= Html::encode($detail['hash'] ?? '') ?></h2>
            </div>
            <div class="mapsV2__detail-actions">
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
                <?php
                $hasFullImage = !empty($detail['imageIconUrl']) || !empty($detail['rawImageUrl']) || !empty($detail['image']);
                $fullImageSrc = $detail['imageIconUrl'] ?? $detail['rawImageUrl'] ?? $detail['image'] ?? $detail['imagePreview'] ?? '';
                ?>
                <?php if ($hasFullImage): ?>
                    <button class="mapsV2__open-full"
                            type="button"
                            data-action="open-fullscreen"
                            data-src="<?= Html::encode($fullImageSrc) ?>">
                        <i class="fas fa-expand"></i>
                        <span><?= Yii::t('common', 'Открыть полностью') ?></span>
                    </button>
                <?php endif; ?>
            </div>
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
            <div class="mapsV2__monuments" data-role="monuments">
                <h3><?= Yii::t('common', 'Монументы') ?></h3>
                <div class="mapsV2__monuments-list" data-role="monuments-list">
                    <?php foreach (array_slice($detail['monuments'], 0, 40) as $index => $monument): ?>
                        <span class="mapsV2__monument-chip"
                              title="<?= Html::encode($monument['label'] ?? $monument['type'] ?? '') ?>"
                              data-monument-index="<?= $index ?>">
                            <?= Html::encode($monument['label'] ?? $monument['type'] ?? '') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="mapsV2__voters" data-role="voters">
            <div class="mapsV2__voters-header">
                <h3><?= Yii::t('common', 'Кто уже проголосовал') ?></h3>
                <button type="button" class="mapsV2__voters-refresh" data-action="refresh-voters">
                    <i class="fas fa-sync"></i>
                    <?= Yii::t('common', 'Обновить') ?>
                </button>
            </div>
            <div class="mapsV2__voters-list" data-role="voters-list">
                <?php if (!empty($detail['voters'])): ?>
                    <?php foreach ($detail['voters'] as $voter): ?>
                        <div class="mapsV2__voter">
                            <img src="<?= Html::encode($voter['avatar'] ?? '') ?>" alt="<?= Html::encode($voter['username'] ?? '') ?>">
                            <div>
                                <strong><?= Html::encode($voter['username'] ?? '') ?></strong>
                                <span><?= !empty($voter['created_at']) ? date('d.m H:i', strtotime($voter['created_at'])) : '' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="mapsV2__voters-empty">
                        <?= Yii::t('common', 'Будьте первым, кто проголосует за эту карту') ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>


<?php

use yii\widgets\Pjax;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\helpers\Html;
use yii\helpers\Json;

/** @var \common\models\servers\Servers[] $servers */
/** @var \common\models\servers\Servers $server */
/** @var \common\models\map\MapList[] $maps */
/** @var \common\models\map\MapList|null $currentMap */
/** @var array $voteCounts */
/** @var array $userVotes */
/** @var int|null $userVotedMapId */
/** @var array $userVotedMapIds */
/** @var array $mapDetails */
/** @var array $mapCardsData */
/** @var int $maxVotes */
/** @var int $totalVotes */
/** @var array $biomeLabels */
/** @var int $totalMaps */
/** @var int $displayLimit */
/** @var string $voteUrlTemplate */
/** @var string $votersUrlTemplate */
/** @var string $mapsPayloadJson */
/** @var array $cardsHtml */

$hasMaps = !empty($maps);
$detail = null;
if ($hasMaps && $currentMap) {
    $detail = $mapCardsData[$currentMap->id] ?? null;
}

?>

<div class="mapsV2" id="mapsV2Root"
     data-server-id="<?= $server->id ?>"
     data-server-tag="<?= Html::encode($server->tag) ?>"
     data-vote-url="<?= Html::encode($voteUrlTemplate) ?>"
     data-voters-url="<?= Html::encode($votersUrlTemplate) ?>"
     data-user-voted-id="<?= Html::encode($userVotedMapId ?? '') ?>"
     data-user-voted-ids='<?= Html::encode(Json::encode($userVotedMapIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
     data-text-vote="<?= Yii::t('common', 'Проголосовать') ?>"
     data-text-download="<?= Yii::t('common', 'Скачать карту') ?>"
     data-text-empty-voters="<?= Yii::t('common', 'Будьте первым, кто проголосует за эту карту') ?>"
     data-text-no-votes="<?= Yii::t('common', 'Пока никто не голосовал') ?>"
     data-text-refresh="<?= Yii::t('common', 'Обновить') ?>"
     data-biome-labels='<?= Html::encode(Json::encode($biomeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
     data-total-maps="<?= $totalMaps ?>"
     data-total-votes="<?= $totalVotes ?>"
     data-display-limit="<?= $displayLimit ?>"
     data-maps='<?= Html::encode($mapsPayloadJson) ?>'>

    <header class="mapsV2__hero">
        <div class="mapsV2__hero-primary">
            <p class="mapsV2__eyebrow"><?= Yii::t('common', 'Голосование карт v2') ?></p>
            <h1 class="mapsV2__title">
                <?= Yii::t('common', 'Выбор следующей карты') ?>
                <span><?= Yii::t('database', $server->name) ?></span>
            </h1>
        </div>
        <dl class="mapsV2__meta">
            <div class="mapsV2__meta-card">
                <dt class="mapsV2__meta-label"><?= Yii::t('common', 'Размер карты') ?></dt>
                <dd class="mapsV2__meta-value"><?= $server->min_map_size ?> – <?= $server->max_map_size ?></dd>
            </div>
            <div class="mapsV2__meta-card">
                <dt class="mapsV2__meta-label"><?= Yii::t('common', 'Следующий вайп') ?></dt>
                <dd class="mapsV2__meta-value"><?= date('d.m.Y H:i', strtotime($server->next_wipe)) ?> МСК</dd>
            </div>
            <div class="mapsV2__meta-card">
                <dt class="mapsV2__meta-label"><?= Yii::t('common', 'Условия голосования') ?></dt>
                <dd class="mapsV2__meta-value"><?= Yii::t('common', '1+ час игры на сервере') ?></dd>
            </div>
        </dl>

        <nav class="mapsV2__servers">
            <?php foreach ($servers as $item): ?>
                <a class="mapsV2__server-link<?= $item->tag === $server->tag ? ' is-active' : '' ?>"
                   href="/maps-v2/<?= Html::encode($item->tag) ?>">
                    <span><?= Yii::t('database', $item->monitoring_name) ?></span>
                    <small><?= $item->min_map_size ?>–<?= $item->max_map_size ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($totalMaps > $displayLimit): ?>
            <p class="mapsV2__note">
                <?= Yii::t('common', 'Показаны {shown} из {total} доступных карт.', ['shown' => $displayLimit, 'total' => $totalMaps]) ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if (!$hasMaps): ?>
        <section class="mapsV2__empty">
            <div class="mapsV2__empty-icon">
                <i class="fas fa-map"></i>
            </div>
            <h2><?= Yii::t('common', 'Пока нет карт в этом диапазоне') ?></h2>
            <p><?= Yii::t('common', 'Как только карты появятся, вы сможете проголосовать за любимый ландшафт.') ?></p>
        </section>
    <?php else: ?>
        <div class="mapsV2__layout">
            <?php
            $pjaxId = 'maps-v2-cards-pjax';
            
            Pjax::begin([
                'id' => $pjaxId,
                'enablePushState' => false,
                'timeout' => 5000
            ]);
            ?>
            
            <?= Alert::widget() ?>
            
            <?php
            $form = ActiveForm::begin([
                'id' => 'vote-form',
                'action' => '/maps-v2/vote',
                'method' => 'post',
                'enableClientValidation' => false,
                'enableAjaxValidation' => false,
                'options' => [
                    'data-pjax' => 1,
                ],
            ]);
            ?>
            
            <?= Html::hiddenInput('server_id', $server->id) ?>
            
            <div class="mapsV2__cards" data-role="map-list">
                <?php foreach ($maps as $map): ?>
                    <?= $cardsHtml[$map->id] ?? '' ?>
                <?php endforeach; ?>
            </div>
            
            <?php ActiveForm::end(); ?>
            
            <?php Pjax::end(); ?>
        </div>
    <?php endif; ?>

    <div class="mapsV2__modal" data-role="detail-modal" aria-hidden="true">
        <div class="mapsV2__modal-backdrop" data-action="close-modal"></div>
        <div class="mapsV2__modal-dialog" role="dialog" aria-modal="true">
            <button class="mapsV2__modal-close" type="button" data-action="close-modal" aria-label="<?= Yii::t('common', 'Закрыть') ?>">
                <i class="fas fa-times"></i>
            </button>
            <section class="mapsV2__detail" data-role="map-detail">
                <?php if ($hasMaps && $detail): ?>
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
                        <div class="mapsV2__navigation">
                            <button class="mapsV2__nav mapsV2__nav--prev" type="button" data-action="prev-map">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="mapsV2__nav mapsV2__nav--next" type="button" data-action="next-map">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mapsV2__detail-body" data-map-detail-id="<?= $detail['id'] ?>">
                        <header class="mapsV2__detail-header">
                            <div>
                                <p class="mapsV2__detail-type" data-role="detail-type"><?= Html::encode($detail['type'] ?? 'Procedural') ?></p>
                                <h2 class="mapsV2__detail-title" data-role="detail-title"><?= Html::encode($detail['hash'] ?? '') ?></h2>
                            </div>
                            <div class="mapsV2__detail-actions">
                                <button class="mapsV2__vote-button<?= ($userVotedMapId && $userVotedMapId == $detail['id']) ? ' is-active' : '' ?>"
                                        data-action="vote"
                                        data-map-id="<?= $detail['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                    <span><?= Yii::t('common', 'Проголосовать') ?></span>
                                </button>
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
                                <button class="mapsV2__open-full<?= !$hasFullImage ? ' is-hidden' : '' ?>"
                                        type="button"
                                        data-action="open-fullscreen"
                                        data-src="<?= Html::encode($fullImageSrc) ?>">
                                    <i class="fas fa-expand"></i>
                                    <span><?= Yii::t('common', 'Открыть полностью') ?></span>
                                </button>
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
                <?php else: ?>
                    <div class="mapsV2__empty mapsV2__empty--detail">
                        <div class="mapsV2__empty-icon">
                            <i class="fas fa-map"></i>
                        </div>
                        <h2><?= Yii::t('common', 'Выберите карту') ?></h2>
                        <p><?= Yii::t('common', 'Нажмите на кнопку «Подробнее» у любой карты.') ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>


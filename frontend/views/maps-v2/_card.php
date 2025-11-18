<?php

use yii\helpers\Html;

/** @var \common\models\map\MapList $map */
/** @var array $mapCardsData */
/** @var array $voteCounts */
/** @var array $userVotes */
/** @var array $userVotedMapIds */
/** @var \common\models\map\MapList|null $currentMap */
/** @var int $maxVotes */
/** @var int $totalVotes */
/** @var \common\models\servers\Servers $server */

$card = $mapCardsData[$map->id] ?? [];
$votes = $voteCounts[$map->id] ?? 0;
$voters = $userVotes[$map->id] ?? [];
$progress = $totalVotes > 0 ? ($votes / $totalVotes * 100) : 0;

$isActive = $currentMap && $currentMap->id === $map->id;
$isLeading = $votes > 0 && $votes === $maxVotes && $maxVotes > 0;
$isVoted = !empty($userVotedMapIds) && in_array($map->id, $userVotedMapIds);

?>

<article class="mapsV2__card<?= $isActive ? ' is-active' : '' ?><?= $isLeading ? ' is-leading' : '' ?>"
         data-map-id="<?= $map->id ?>">
    <div class="mapsV2__card-image-wrapper">
        <button class="mapsV2__card-image"
                type="button"
                data-action="open-detail"
                data-map-id="<?= $map->id ?>"
                data-pjax="0"
                aria-label="<?= Yii::t('common', 'Открыть подробную информацию') ?>">
            <img src="<?= Html::encode($card['imagePreview'] ?? $card['image'] ?? '') ?>" alt="<?= Yii::t('common', 'Превью карты') ?>">
        </button>
        
        <button type="submit" 
                name="map_id"
                value="<?= $map->id ?>"
                class="mapsV2__card-chip mapsV2__card-chip--votes<?= $isVoted ? ' is-active' : '' ?><?= $isLeading ? ' is-leading' : '' ?>"
                aria-label="<?= Yii::t('common', 'Проголосовать за карту') ?>">
            <i class="fas fa-heart"></i>
            <span data-role="card-votes"><?= $votes ?></span>
        </button>
        
        <?php if (!empty($card['isStaging'])): ?>
            <span class="mapsV2__card-chip mapsV2__card-chip--warning">Staging</span>
        <?php endif; ?>
    </div>
    <div class="mapsV2__card-body">
        <p class="mapsV2__card-meta">
            <?= Yii::t('common', 'Размер') ?>: <?= Html::encode($card['size'] ?? '') ?> x <?= Html::encode($card['size'] ?? '') ?>
        </p>
        <div class="mapsV2__card-progress">
            <div class="mapsV2__card-progress-bar" style="--progress: <?= $progress ?>%;"></div>
        </div>
        <div class="mapsV2__card-votes">
            <strong data-role="card-votes-total"><?= $votes ?></strong>
            <span><?= Yii::t('common', 'голосов') ?></span>
        </div>
        <div class="mapsV2__card-voters" data-role="card-voters">
            <?php if (!empty($voters)): ?>
                <?php foreach (array_slice($voters, 0, 5) as $voter): ?>
                    <img src="<?= Html::encode($voter['avatar'] ?? '') ?>"
                         alt="<?= Html::encode($voter['username'] ?? '') ?>"
                         title="<?= Html::encode($voter['username'] ?? '') ?>">
                <?php endforeach; ?>
                <?php if (count($voters) > 5): ?>
                    <span class="mapsV2__card-more">+<?= count($voters) - 5 ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="mapsV2__card-voters-empty"><?= Yii::t('common', 'Пока никто не голосовал') ?></span>
            <?php endif; ?>
        </div>
    </div>
</article>


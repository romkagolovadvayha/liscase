<?php

use yii\widgets\Pjax;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var array $voters */
/** @var int $mapId */
/** @var int $serverId */
/** @var bool $isVoted */

?>
<?php Pjax::begin([
    'id' => 'maps-v2-voters-pjax-' . $mapId,
    'enablePushState' => false,
    'timeout' => 5000
]); ?>

<section class="mapsV2__voters" data-role="voters">
    <div class="mapsV2__voters-header">
        <h3><?= Yii::t('common', 'Кто уже проголосовал') ?></h3>
        <?php $form = ActiveForm::begin([
            'id' => 'vote-form-detail-' . $mapId,
            'action' => '/maps-v2/vote-detail',
            'method' => 'post',
            'enableClientValidation' => false,
            'enableAjaxValidation' => false,
            'options' => [
                'data-pjax' => 1,
            ],
        ]); ?>
        <?= Html::hiddenInput('server_id', $serverId) ?>
        <?= Html::hiddenInput('map_id', $mapId) ?>
        <button type="submit" class="mapsV2__vote-button<?= $isVoted ? ' is-active' : '' ?>" data-action="vote-from-detail" data-map-id="<?= $mapId ?>">
            <i class="<?= $isVoted ? 'fas' : 'far' ?> fa-heart"></i>
            <?= Yii::t('common', 'Проголосовать') ?>
        </button>
        <?php ActiveForm::end(); ?>
    </div>
    <div class="mapsV2__voters-list" data-role="voters-list">
        <?php if (!empty($voters)): ?>
            <?php foreach ($voters as $voter): ?>
                <div class="mapsV2__voter-tag" title="<?= Html::encode($voter['username'] ?? '') ?>">
                    <?php 
                    $avatarUrl = $voter['avatar'] ?? ''; 
                    $hasAvatar = !empty($avatarUrl);
                    $firstLetter = mb_strtoupper(mb_substr($voter['username'] ?? '?', 0, 1));
                    ?>
                    <?php if ($hasAvatar): ?>
                        <img src="<?= Html::encode($avatarUrl) ?>" 
                             alt="<?= Html::encode($voter['username'] ?? '') ?>"
                             class="mapsV2__voter-tag-avatar"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    <span class="mapsV2__voter-tag-avatar-fallback" style="<?= $hasAvatar ? 'display: none;' : 'display: flex;' ?>">
                        <?= $firstLetter ?>
                    </span>
                    <span class="mapsV2__voter-tag-username"><?= Html::encode($voter['username'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="mapsV2__voters-empty">
                <?= Yii::t('common', 'Будьте первым, кто проголосует за эту карту') ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php Pjax::end(); ?>


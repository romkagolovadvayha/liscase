<?php

use yii\helpers\Html;

/** @var \common\models\radio\RadioTrack $model */
/** @var array $userLikes */
/** @var \common\models\radio\RadioStation $station */

$isActive = in_array($model->id, $userLikes);
$isOwner = !Yii::$app->user->isGuest && $model->user_id == Yii::$app->user->id;
?>

<div class="radio-track-item">
    <div class="track-header">
        <div class="track-icon">
            <i class="fa fa-music"></i>
        </div>
        <div class="track-info">
            <div class="track-title"><?= Html::encode($model->title) ?></div>
            <?php if ($model->artist): ?>
                <div class="track-artist"><?= Html::encode($model->artist) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="track-meta">
        <div class="track-duration">
            <i class="fa fa-clock"></i>
            <?= $model->getFormattedDuration() ?>
        </div>
        <div class="track-plays">
            <i class="fa fa-play-circle"></i>
            <?= $model->plays ?>
        </div>
    </div>

    <div class="track-footer">
        <a
            title="<?= Yii::t('common', 'Открыть профиль Steam') ?>"
            target="_blank"
            class="track-user"
            rel="nofollow"
            href="<?= $model->user->getLink('stats') ?>">
            <img src="<?= $model->user->getAvatar() ?>" title="<?= $model->user->username ?>"/>
            <span class="track-username"><?= Html::encode($model->user->username) ?></span>
        </a>

        <div class="track-actions">
            <?php if ($station->is_running): ?>
                <a href="/radio/queue-first-pay?id=<?= $model->id ?>"
                   class="track-queue-first-btn show-modal-link"
                   data-size="modal-sm"
                   data-content-overflow="unset"
                   data-top-image="<?= Yii::$app->settings->get('design_payPopupImage') ?>"
                   data-top-class="modal-backdrop-image_pay active"
                   data-toggl="modal"
                   data-target="modal-dialog"
                   data-title="<?= Yii::t('common', 'Поставить трек первым в очередь') ?>"
                   title="<?= Yii::t('common', 'Поставить первым в очередь - 30 монет') ?>">
                    <i class="fa fa-forward"></i>
                </a>
            <?php endif; ?>

            <div 
                class="track-like-btn<?= $isActive ? ' active' : '' ?>" 
                data-track-id="<?= $model->id ?>" 
                data-guest="<?= Yii::$app->user->isGuest ? 1 : 0 ?>"
                title="<?= Yii::t('common', 'Лайк') ?>">
                <span class="like-count"><?= $model->likes ?></span>
                <span class="like-icon">
                    <i class="icon_active fa-solid fa-heart"></i>
                    <i class="icon_noactive fa-regular fa-heart"></i>
                </span>
            </div>

            <?php if ($isOwner && $model->status != \common\models\radio\RadioTrack::STATUS_ACTIVE): ?>
                <?= Html::a(
                    '<i class="fa fa-trash"></i>',
                    ['delete', 'id' => $model->id],
                    [
                        'class' => 'track-delete-btn',
                        'title' => Yii::t('common', 'Удалить'),
                        'data' => [
                            'confirm' => Yii::t('common', 'Вы уверены, что хотите удалить этот трек?'),
                            'method' => 'post',
                        ],
                    ]
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="track-date">
        <?= $model->passed() ?>
    </div>
</div>

<style>
.radio-track-item {
    background: var(--background-secondary);
    border: 1px solid var(--border-primary);
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
    position: relative;
}

.radio-track-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.track-header {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.track-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary-colors-main), var(--primary-colors-dark));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.track-info {
    flex: 1;
    min-width: 0;
}

.track-title {
    font-weight: 600;
    font-size: 16px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.track-artist {
    color: var(--text-secondary);
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.track-meta {
    display: flex;
    gap: 16px;
    margin-bottom: 12px;
    font-size: 13px;
    color: var(--text-secondary);
}

.track-meta > div {
    display: flex;
    align-items: center;
    gap: 4px;
}

.track-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--border-primary);
}

.track-user {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: inherit;
    transition: opacity 0.2s;
}

.track-user:hover {
    opacity: 0.7;
}

.track-user img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

.track-username {
    font-size: 14px;
    font-weight: 500;
}

.track-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.track-like-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 20px;
    background: var(--background-teritiary);
    transition: all 0.2s;
    user-select: none;
}

.track-like-btn:hover {
    background: var(--background-primary);
}

.track-like-btn .icon_active {
    display: none;
    color: #e74c3c;
}

.track-like-btn .icon_noactive {
    display: inline;
    color: var(--text-secondary);
}

.track-like-btn.active .icon_active {
    display: inline;
}

.track-like-btn.active .icon_noactive {
    display: none;
}

.like-count {
    font-size: 14px;
    font-weight: 600;
}

.track-queue-first-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.track-queue-first-btn:hover {
    background: #218838;
    transform: scale(1.05);
}

.track-queue-first-btn:active {
    transform: scale(0.95);
}

.track-delete-btn {
    color: #dc3545;
    padding: 6px 10px;
    border-radius: 6px;
    transition: all 0.2s;
}

.track-delete-btn:hover {
    background: rgba(220, 53, 69, 0.1);
}

.track-date {
    position: absolute;
    top: 16px;
    right: 16px;
    font-size: 12px;
    color: var(--text-secondary);
}
</style>


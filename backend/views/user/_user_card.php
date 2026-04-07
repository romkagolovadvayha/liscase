<?php

use common\components\helpers\Role;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use common\models\user\User;
use common\models\user\UserSearch;

/** @var UserSearch $model */
$avatar = $model->getAvatar();
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
$isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
$canLink = $isAdmin || $isModerator;
$statusLabel = ArrayHelper::getValue(User::getStatusList(), $model->status);
$statusClass = $model->status == User::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
$profileUrl = Url::to('/profile/' . $model->id);
$switchUrl = Url::toRoute(['/user/switch-identity', 'id' => $model->id]);
$showSwitch = $model->status == UserSearch::STATUS_ACTIVE && $canLink && !$model->isSwitchIdentityForbidden();
?>
<div class="user-index-card">
    <div class="user-index-card__avatar">
        <?php if (!empty($avatar)): ?>
            <img src="<?= Html::encode($avatar) ?>" alt="" width="44" height="44" loading="lazy" />
        <?php else: ?>
            <span class="user-index-card__avatar-placeholder">—</span>
        <?php endif; ?>
    </div>
    <div class="user-index-card__body">
        <div class="user-index-card__row">
            <?php if ($canLink): ?>
                <?= Html::a(Html::encode($model->username), $profileUrl, ['class' => 'user-index-card__name']) ?>
            <?php else: ?>
                <span class="user-index-card__name"><?= Html::encode($model->username) ?></span>
            <?php endif; ?>
            <span class="ds-badge <?= $statusClass ?>"><?= Html::encode($statusLabel) ?></span>
        </div>
        <div class="user-index-card__meta">
            <span class="user-index-card__id">#<?= (int)$model->id ?></span>
            <a href="https://steamcommunity.com/profiles/<?= Html::encode($model->steam_id) ?>" target="_blank" rel="noopener" class="user-index-card__steam"><?= Html::encode($model->steam_id) ?></a>
        </div>
        <?php if ($model->created_at): ?>
            <div class="user-index-card__date"><?= Yii::$app->formatter->asDate($model->created_at) ?></div>
        <?php endif; ?>
    </div>
    <?php if ($showSwitch): ?>
    <div class="user-index-card__action">
        <?= Html::a('Войти', $switchUrl, ['class' => 'ds-btn ds-btn--primary ds-btn--sm']) ?>
    </div>
    <?php endif; ?>
</div>

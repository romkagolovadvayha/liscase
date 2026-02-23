<?php

use common\models\invoice\Deposit;
use common\components\helpers\Role;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

/** @var Deposit $model */
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
$isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
$canLink = $isAdmin || $isModerator;

$username = $model->user ? Html::encode($model->user->username) : '—';
if ($model->user && $canLink) {
    $username = Html::a($username, ['/user/profile', 'userId' => $model->user->id], ['class' => 'deposit-index-card__link']);
}

$statusLabel = ArrayHelper::getValue(Deposit::getStatusList(), $model->status, '');
$statusClass = $model->status == Deposit::STATUS_SUCCESS
    ? 'ds-badge--success'
    : ($model->status == Deposit::STATUS_WAIT_CONFIRM ? 'ds-badge--warning' : 'ds-badge--danger');

$paymentTypeLabel = ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type, $model->payment_type);
?>
<div class="deposit-index-card">
    <div class="deposit-index-card__head">
        <span class="deposit-index-card__id">#<?= (int)$model->id ?></span>
        <span class="ds-badge <?= $statusClass ?>"><?= Html::encode($statusLabel) ?></span>
    </div>
    <div class="deposit-index-card__row">
        <span class="deposit-index-card__label"><?= Yii::t('common', 'Пользователь') ?></span>
        <span class="deposit-index-card__value"><?= $username ?></span>
    </div>
    <?php if ($model->user): ?>
    <div class="deposit-index-card__row">
        <span class="deposit-index-card__label">Steam ID</span>
        <span class="deposit-index-card__value">
            <?= Html::a(Html::encode($model->user->steam_id), 'https://steamcommunity.com/profiles/' . $model->user->steam_id, ['target' => '_blank', 'class' => 'deposit-index-card__link']) ?>
        </span>
    </div>
    <?php endif; ?>
    <div class="deposit-index-card__row">
        <span class="deposit-index-card__label"><?= Yii::t('common', 'Сумма') ?></span>
        <span class="deposit-index-card__amount"><?= Html::encode($model->amount) ?> ₽</span>
    </div>
    <div class="deposit-index-card__row">
        <span class="deposit-index-card__label"><?= Yii::t('common', 'Тип') ?></span>
        <span class="deposit-index-card__value"><?= Html::encode($paymentTypeLabel) ?></span>
    </div>
    <?php if ($model->status == Deposit::STATUS_WAIT_CONFIRM && !empty($model->payment_id)): ?>
        <?php $checkResult = $model->debugCheck(); $resultName = $checkResult === 'partially-paid' ? Yii::t('common', 'Частично оплачен') : $checkResult; ?>
        <div class="deposit-index-card__row deposit-index-card__debug">
            <span class="deposit-index-card__value"><?= Html::encode($resultName) ?></span>
        </div>
    <?php endif; ?>
    <div class="deposit-index-card__date"><?= Yii::$app->formatter->asDatetime($model->created_at, 'php:Y-m-d H:i') ?></div>
    <div class="deposit-index-card__actions">
        <?php if ($model->status != Deposit::STATUS_SUCCESS): ?>
            <?= Html::a(
                '<i class="fas fa-check"></i> ' . Yii::t('common', 'Принять'),
                Url::toRoute(['accept', 'id' => $model->id]),
                [
                    'class' => 'ds-btn ds-btn--success ds-btn--sm',
                    'data-confirm' => Yii::t('common', 'Вы уверены, что хотите принять этот депозит?'),
                    'data-method' => 'post',
                ]
            ) ?>
        <?php endif; ?>
        <?= Html::a(Yii::t('common', 'Просмотр'), Url::toRoute(['view', 'id' => $model->id]), ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
        <?= Html::a(Yii::t('common', 'Изменить'), Url::toRoute(['update', 'id' => $model->id]), ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
    </div>
</div>

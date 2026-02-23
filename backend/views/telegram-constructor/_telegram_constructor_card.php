<?php

use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

/** @var \backend\models\TelegramConstructorSearch $model */
$botName = ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id);
$audienceName = ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $model->audience_id);
$statusName = ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status);
$statusClass = 'ds-badge--primary';
if ($model->status == TelegramConstructor::STATUS_SUCCESS) $statusClass = 'ds-badge--success';
elseif ($model->status == TelegramConstructor::STATUS_ERROR) $statusClass = 'ds-badge--danger';
elseif ($model->status == TelegramConstructor::STATUS_IN_PROGRESS) $statusClass = 'ds-badge--warning';
$botClass = $model->bot_id == TelegramConstructor::VK_GROUP ? 'ds-badge--info' : 'ds-badge--primary';
$isNew = $model->status === TelegramConstructor::STATUS_NEW;
$canAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
?>
<div class="tc-index-card">
    <div class="tc-index-card__head">
        <?= Html::a(Html::encode($model->title), ['/telegram-constructor/view', 'id' => $model->id], ['class' => 'tc-index-card__title']) ?>
        <span class="ds-badge <?= $statusClass ?>"><?= Html::encode($statusName) ?></span>
    </div>
    <div class="tc-index-card__meta">
        <span class="tc-index-card__id">#<?= (int)$model->id ?></span>
        <span class="ds-badge <?= $botClass ?>"><?= Html::encode($botName) ?></span>
        <span class="ds-badge ds-badge--primary"><?= Html::encode($audienceName) ?></span>
    </div>
    <div class="tc-index-card__row">
        <span class="tc-index-card__label"><?= Yii::t('common', 'Сообщение') ?></span>
        <span class="tc-index-card__value">
            <?php if (empty($model->telegramConstructorMessage)): ?>
                <span class="ds-badge ds-badge--danger">Удалено</span>
            <?php else: ?>
                <?= Html::a(Html::encode($model->telegramConstructorMessage->title), ['/telegram-constructor-message/update', 'id' => $model->telegramConstructorMessage->id], ['class' => 'tc-index-card__link']) ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="tc-index-card__date"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
    <?php if ($isNew): ?>
    <div class="tc-index-card__actions">
        <?php if ($canAdmin): ?>
            <?= Html::a('<i class="fas fa-play"></i>', ['run', 'id' => $model->id], ['class' => 'ds-btn ds-btn--success ds-btn--sm', 'title' => Yii::t('common', 'Запустить'), 'data' => ['confirm' => Yii::t('common', 'Запустить рассылку?'), 'method' => 'post']]) ?>
        <?php endif; ?>
        <?= Html::a('<i class="fas fa-pencil-alt"></i>', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary ds-btn--sm', 'title' => Yii::t('common', 'Редактировать')]) ?>
        <?php if ($canAdmin): ?>
            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'title' => Yii::t('common', 'Удалить'), 'data' => ['confirm' => Yii::t('common', 'Удалить рассылку?'), 'method' => 'post']]) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

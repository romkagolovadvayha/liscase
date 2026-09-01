<?php

use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var TelegramConstructor $model */
$statusClasses = [
    TelegramConstructor::STATUS_NEW => 'is-draft',
    TelegramConstructor::STATUS_IN_PROGRESS => 'is-progress',
    TelegramConstructor::STATUS_SUCCESS => 'is-success',
    TelegramConstructor::STATUS_ERROR => 'is-error',
];
$isDraft = $model->status === TelegramConstructor::STATUS_NEW;
$canDuplicate = Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_CONTENT_MANAGER);
$isVk = $model->bot_id === TelegramConstructor::VK_GROUP;
$channelLabel = $isVk ? 'ВКонтакте' : 'Telegram';
$channelIcon = $isVk ? 'fa-brands fa-vk' : 'fa-brands fa-telegram';
?>
<article class="mailing-campaign-row">
    <a class="mailing-campaign-row__body" href="<?= Html::encode(Url::to(['view', 'id' => $model->id])) ?>">
        <div class="mailing-campaign-row__title">
            <strong><?= Html::encode($model->title) ?></strong>
            <span>#<?= (int)$model->id ?> · <?= Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y, H:i') ?></span>
        </div>
        <div class="mailing-campaign-row__route">
            <span class="mailing-channel"><i class="<?= $channelIcon ?>" aria-hidden="true"></i> <?= Html::encode($channelLabel) ?></span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            <span><?= Html::encode(TelegramConstructor::getAudienceName($model->audience_id)) ?></span>
        </div>
        <div class="mailing-campaign-row__template">
            <i class="fa-regular fa-message" aria-hidden="true"></i>
            <span><?= $model->telegramConstructorMessage ? Html::encode($model->telegramConstructorMessage->title) : 'Шаблон удалён' ?></span>
        </div>
        <span class="mailing-status <?= $statusClasses[$model->status] ?? '' ?>"><?= Html::encode(ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status, 'Неизвестно')) ?></span>
    </a>
    <div class="mailing-campaign-row__actions">
        <?php if ($isDraft): ?>
            <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
        <?php elseif ($canDuplicate): ?>
            <?= Html::a('<i class="fa-regular fa-copy" aria-hidden="true"></i> Повторить', ['duplicate', 'id' => $model->id], [
                'class' => 'ds-btn ds-btn--ghost ds-btn--sm',
                'data' => ['method' => 'post'],
            ]) ?>
        <?php endif; ?>
        <?= Html::a('<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>', ['view', 'id' => $model->id], ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'aria-label' => 'Открыть рассылку', 'title' => 'Открыть']) ?>
    </div>
</article>

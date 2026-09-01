<?php

use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var TelegramConstructor $model */
$statusClasses = [
    TelegramConstructor::STATUS_NEW => 'is-draft',
    TelegramConstructor::STATUS_IN_PROGRESS => 'is-progress',
    TelegramConstructor::STATUS_SUCCESS => 'is-success',
    TelegramConstructor::STATUS_ERROR => 'is-error',
];
$isDraft = $model->status === TelegramConstructor::STATUS_NEW;
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
?>
<article class="mailing-campaign-card">
    <header>
        <div>
            <span class="mailing-campaign-card__id">#<?= (int)$model->id ?></span>
            <?= Html::a(Html::encode($model->title), ['view', 'id' => $model->id], ['class' => 'mailing-campaign-card__title']) ?>
        </div>
        <span class="mailing-status <?= $statusClasses[$model->status] ?? '' ?>"><?= Html::encode(ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status, 'Неизвестно')) ?></span>
    </header>
    <dl>
        <div><dt>Канал</dt><dd><?= Html::encode(ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id, 'Неизвестно')) ?></dd></div>
        <div><dt>Аудитория</dt><dd><?= Html::encode(TelegramConstructor::getAudienceName($model->audience_id)) ?></dd></div>
        <div><dt>Шаблон</dt><dd><?= $model->telegramConstructorMessage ? Html::encode($model->telegramConstructorMessage->title) : '<span class="mailing-inline-error">Удалён</span>' ?></dd></div>
        <div><dt>Создана</dt><dd><?= Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y, H:i') ?></dd></div>
    </dl>
    <footer>
        <?= Html::a('<i class="fa-solid fa-eye" aria-hidden="true"></i> Открыть', ['view', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?>
        <?php if ($isDraft): ?>
            <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--ghost ds-btn--sm']) ?>
            <?php if ($isAdmin): ?>
                <?= Html::a('<i class="fa-solid fa-play" aria-hidden="true"></i> Запустить', ['play', 'id' => $model->id], ['class' => 'ds-btn ds-btn--success ds-btn--sm', 'data' => ['confirm' => 'Запустить рассылку? После запуска изменить или повторно отправить её нельзя.', 'method' => 'post']]) ?>
            <?php endif; ?>
        <?php endif; ?>
    </footer>
</article>

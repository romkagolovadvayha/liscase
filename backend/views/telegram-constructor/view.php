<?php

use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use common\helpers\HStrings;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var TelegramConstructor $model */
/** @var int $audienceCount */

$this->title = $model->title;
$this->params['contentClass'] = 'content-no-padding';
$isDraft = $model->status === TelegramConstructor::STATUS_NEW;
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
$canLaunch = $isDraft && $isAdmin && $audienceCount > 0 && $model->telegramConstructorMessage !== null;
$hasLaunchBlocker = $isDraft && ($audienceCount === 0 || $model->telegramConstructorMessage === null);
$statusClasses = [
    TelegramConstructor::STATUS_NEW => 'is-draft',
    TelegramConstructor::STATUS_IN_PROGRESS => 'is-progress',
    TelegramConstructor::STATUS_SUCCESS => 'is-success',
    TelegramConstructor::STATUS_ERROR => 'is-error',
];
?>
<div class="mailing-page mailing-review-page">
    <?= $this->render('_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-review-head">
        <div>
            <div class="mailing-review-head__meta">
                <span>#<?= (int)$model->id ?></span>
                <span class="mailing-status <?= $statusClasses[$model->status] ?? '' ?>"><?= Html::encode(ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status, 'Неизвестно')) ?></span>
            </div>
            <h1><?= Html::encode($model->title) ?></h1>
            <p><?= $isDraft ? 'Проверьте все параметры. После запуска изменить или повторно отправить рассылку нельзя.' : 'Параметры отправленной рассылки доступны только для просмотра.' ?></p>
        </div>
        <div class="mailing-review-head__actions">
            <?= Html::a('<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> К списку', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            <?php if ($isDraft): ?>
                <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary']) ?>
            <?php endif; ?>
            <?php if ($canLaunch): ?>
                <?= Html::a('<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Запустить рассылку', ['play', 'id' => $model->id], [
                    'class' => 'ds-btn ds-btn--success',
                    'data' => ['confirm' => 'Отправить сообщение ' . Yii::$app->formatter->asInteger($audienceCount) . ' ' . HStrings::pluralForm($audienceCount, ['получателю', 'получателям', 'получателям']) . '? Отменить запуск будет нельзя.', 'method' => 'post'],
                ]) ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($hasLaunchBlocker): ?>
        <div class="mailing-blocker" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div><strong>Рассылку пока нельзя запустить</strong><span><?= $model->telegramConstructorMessage === null ? 'Выбранный шаблон удалён. Откройте черновик и выберите другой.' : 'В выбранной аудитории нет доступных получателей.' ?></span></div>
        </div>
    <?php elseif ($isDraft && !$isAdmin): ?>
        <div class="mailing-info-strip" role="status">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Черновик готов к запуску. Отправку может подтвердить администратор.</span>
        </div>
    <?php endif; ?>

    <div class="mailing-review-grid">
        <main>
            <section class="mailing-review-section" aria-labelledby="mailing-review-summary">
                <header><h2 id="mailing-review-summary">Параметры отправки</h2><span>Проверьте перед запуском</span></header>
                <dl class="mailing-summary-list">
                    <div><dt>Канал</dt><dd><?= Html::encode(ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id, 'Неизвестно')) ?></dd></div>
                    <div><dt>Аудитория</dt><dd><?= Html::encode(TelegramConstructor::getAudienceName($model->audience_id)) ?></dd></div>
                    <div><dt>Получателей сейчас</dt><dd><strong><?= Yii::$app->formatter->asInteger($audienceCount) ?></strong> <?= Html::a('Открыть список', ['audience', 'id' => $model->id], ['class' => 'mailing-table-link']) ?></dd></div>
                    <div><dt>Шаблон</dt><dd><?= $model->telegramConstructorMessage ? Html::a(Html::encode($model->telegramConstructorMessage->title), ['/telegram-constructor-message/view', 'id' => $model->telegramConstructorMessage->id], ['class' => 'mailing-table-link']) : '<span class="mailing-inline-error">Шаблон удалён</span>' ?></dd></div>
                    <?php if ($model->bot_id === TelegramConstructor::VK_GROUP): ?>
                        <div><dt>Фильтр VK</dt><dd><?= $model->only_with_user ? 'Только с аккаунтом на сайте' : 'Все доступные участники' ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Создана</dt><dd><?= Yii::$app->formatter->asDatetime($model->created_at, 'php:d.m.Y, H:i') ?></dd></div>
                </dl>
            </section>

            <?php if ($isDraft && $isAdmin): ?>
                <div class="mailing-review-danger-zone">
                    <div><strong>Удалить черновик</strong><span>Это действие нельзя отменить.</span></div>
                    <?= Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i> Удалить', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => 'Удалить черновик рассылки?', 'method' => 'post']]) ?>
                </div>
            <?php endif; ?>
        </main>

        <aside class="mailing-review-preview" aria-labelledby="mailing-review-preview-title">
            <header><h2 id="mailing-review-preview-title">Сообщение</h2><span>Как его увидит получатель</span></header>
            <?php if ($model->telegramConstructorMessage): ?>
                <?= $this->render('@backend/views/telegram-constructor-message/preview', ['model' => $model->telegramConstructorMessage]) ?>
            <?php else: ?>
                <div class="mailing-preview-empty"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Шаблон сообщения удалён.</span></div>
            <?php endif; ?>
        </aside>
    </div>
</div>

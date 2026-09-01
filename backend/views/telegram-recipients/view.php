<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\TelegramConstructor;
use backend\models\TelegramRecipients;
use common\helpers\HStrings;
use common\models\user\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var TelegramRecipients $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = $model->name;
$this->params['contentClass'] = 'content-no-padding';
$usageCount = $model->getUsageCount();
$resolvedQuantity = $model->getResolvedQuantity();
?>
<div class="mailing-page mailing-audience-view-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-review-head">
        <div>
            <div class="mailing-review-head__meta"><span>Аудитория #<?= (int)$model->id ?></span><span><?= Html::encode($model->getUsageLabel()) ?></span></div>
            <h1><?= Html::encode($model->name) ?></h1>
            <p><?= Yii::$app->formatter->asInteger($resolvedQuantity) ?> <?= HStrings::pluralForm($resolvedQuantity, ['активный пользователь', 'активных пользователя', 'активных пользователей']) ?> до проверки доступности конкретного канала.</p>
        </div>
        <div class="mailing-review-head__actions">
            <?= Html::a('<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> К аудиториям', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            <?= Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a('<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Создать рассылку', ['/telegram-constructor/create', 'audience' => TelegramConstructor::CUSTOM_AUDIENCE_OFFSET + (int)$model->id], ['class' => 'ds-btn ds-btn--success']) ?>
        </div>
    </header>

    <div class="mailing-list-head">
        <div><h2>Участники</h2><span>Активные аккаунты</span></div>
        <span class="mailing-list-head__hint">Доступность Telegram/VK проверяется при запуске</span>
    </div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'layout' => "{items}\n{pager}",
        'options' => ['class' => 'mailing-grid'],
        'tableOptions' => ['class' => 'table mailing-table'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'emptyText' => 'В аудитории нет активных пользователей.',
        'columns' => [
            ['attribute' => 'id', 'label' => 'ID'],
            ['attribute' => 'username', 'label' => 'Пользователь'],
            ['attribute' => 'steam_id', 'label' => 'Steam ID'],
            [
                'label' => 'Telegram',
                'format' => 'raw',
                'value' => static fn(User $user) => $user->telegram_chat_id && !$user->is_telegram_blocked ? '<span class="mailing-status is-success">Доступен</span>' : '<span class="mailing-status is-draft">Недоступен</span>',
            ],
            [
                'label' => 'VK',
                'format' => 'raw',
                'value' => static fn(User $user) => $user->vk_id ? '<span class="mailing-status is-success">Привязан</span>' : '<span class="mailing-status is-draft">Нет связи</span>',
            ],
        ],
    ]) ?>

    <div class="mailing-review-danger-zone mailing-audience-danger">
        <div><strong>Удаление аудитории</strong><span><?= $usageCount > 0 ? 'Недоступно: аудитория уже связана с рассылками.' : 'Аудитория ещё не использовалась и может быть удалена.' ?></span></div>
        <?php if ($usageCount > 0): ?>
            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" disabled><i class="fa-solid fa-lock" aria-hidden="true"></i> Используется</button>
        <?php else: ?>
            <?= Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i> Удалить', ['delete', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger ds-btn--sm', 'data' => ['confirm' => 'Удалить аудиторию?', 'method' => 'post']]) ?>
        <?php endif; ?>
    </div>
</div>

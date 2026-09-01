<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\TelegramConstructor;
use common\helpers\HStrings;
use common\models\vk\VkUser;
use yii\data\ArrayDataProvider;
use yii\helpers\Html;

/** @var int $audienceId */
/** @var int $audienceCount */
/** @var VkUser[] $vkUsers */
/** @var int|null $mailingId */

$this->title = 'Получатели VK — ' . TelegramConstructor::getAudienceName($audienceId);
$this->params['contentClass'] = 'content-no-padding';
$mailingId = $mailingId ?? null;
?>
<div class="mailing-page mailing-audience-preview-page">
    <?= $this->render('_section_nav') ?>
    <header class="mailing-review-head">
        <div>
            <div class="mailing-review-head__meta"><span>Предпросмотр аудитории VK</span><span><?= Yii::$app->formatter->asInteger($audienceCount) ?> <?= HStrings::pluralForm($audienceCount, ['получатель', 'получателя', 'получателей']) ?></span></div>
            <h1><?= Html::encode(TelegramConstructor::getAudienceName($audienceId)) ?></h1>
            <p>Показаны участники, разрешившие сообщения от сообщества.</p>
        </div>
        <div class="mailing-review-head__actions">
            <?= Html::a('<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Вернуться', $mailingId ? ['view', 'id' => $mailingId] : ['create'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </header>
    <?= GridView::widget([
        'dataProvider' => new ArrayDataProvider(['allModels' => $vkUsers, 'pagination' => ['pageSize' => 50]]),
        'layout' => "{items}\n{pager}",
        'options' => ['class' => 'mailing-grid'],
        'tableOptions' => ['class' => 'table mailing-table'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'emptyText' => 'Доступных получателей VK нет.',
        'columns' => [
            ['attribute' => 'vk_user_id', 'label' => 'VK ID'],
            [
                'label' => 'Пользователь',
                'value' => static fn(VkUser $model) => trim($model->first_name . ' ' . $model->last_name),
            ],
            [
                'attribute' => 'screen_name',
                'label' => 'Профиль',
                'format' => 'raw',
                'value' => static fn(VkUser $model) => $model->screen_name ? Html::a('@' . Html::encode($model->screen_name), 'https://vk.com/' . rawurlencode($model->screen_name), ['class' => 'mailing-table-link', 'target' => '_blank', 'rel' => 'noopener']) : '—',
            ],
            [
                'attribute' => 'can_send_message',
                'label' => 'Сообщения',
                'format' => 'raw',
                'value' => static fn(VkUser $model) => $model->can_send_message ? '<span class="mailing-status is-success">Доступны</span>' : '<span class="mailing-status is-error">Недоступны</span>',
            ],
            ['attribute' => 'updated_at', 'label' => 'Проверено', 'format' => ['datetime', 'php:d.m.Y, H:i']],
        ],
    ]) ?>
</div>

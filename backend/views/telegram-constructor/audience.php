<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\AudienceSearch;
use backend\models\TelegramConstructor;
use common\helpers\HStrings;
use common\models\user\User;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\AudienceSearch $searchModel */
/** @var int $audienceId */
/** @var int $audienceCount */
/** @var int|null $mailingId */

$this->title = 'Получатели — ' . TelegramConstructor::getAudienceName($audienceId);
$this->params['contentClass'] = 'content-no-padding';
$mailingId = $mailingId ?? null;
?>
<div class="mailing-page mailing-audience-preview-page">
    <?= $this->render('_section_nav') ?>
    <header class="mailing-review-head">
        <div>
            <div class="mailing-review-head__meta"><span>Предпросмотр аудитории</span><span><?= Yii::$app->formatter->asInteger($audienceCount) ?> <?= HStrings::pluralForm($audienceCount, ['получатель', 'получателя', 'получателей']) ?></span></div>
            <h1><?= Html::encode(TelegramConstructor::getAudienceName($audienceId)) ?></h1>
            <p>Список сформирован с учётом текущей доступности Telegram.</p>
        </div>
        <div class="mailing-review-head__actions">
            <?= Html::a('<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Вернуться', $mailingId ? ['view', 'id' => $mailingId] : ['create'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </header>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'layout' => "{items}\n{pager}",
        'options' => ['class' => 'mailing-grid'],
        'tableOptions' => ['class' => 'table mailing-table'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'emptyText' => 'Доступных получателей нет.',
        'columns' => [
            ['attribute' => 'id', 'label' => 'ID'],
            [
                'attribute' => 'username',
                'format' => 'raw',
                'value' => static fn(AudienceSearch $model) => Html::a(Html::encode($model->username ?: 'Без имени'), Url::to('/profile/' . $model->id), ['class' => 'mailing-table-link']),
            ],
            ['attribute' => 'steam_id', 'label' => 'Steam ID'],
            ['attribute' => 'ref_code', 'label' => 'Реф. код'],
            [
                'attribute' => 'status',
                'value' => static fn(AudienceSearch $model) => User::getStatusList()[$model->status] ?? 'Неизвестно',
            ],
            ['attribute' => 'created_at', 'format' => ['datetime', 'php:d.m.Y, H:i']],
        ],
    ]) ?>
</div>

<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\TelegramConstructor;
use backend\models\TelegramRecipients;
use common\helpers\HStrings;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\TelegramRecipientsSearch $searchModel */

$this->title = 'Аудитории рассылок';
$this->params['contentClass'] = 'content-no-padding';
$totalCount = $dataProvider->getTotalCount();
?>
<div class="mailing-page mailing-audiences-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head">
        <div>
            <span class="mailing-page-head__eyebrow">Получатели</span>
            <h1>Аудитории</h1>
            <p>Сохраняйте отдельные группы пользователей и выбирайте их при создании рассылки.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новая аудитория', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <div class="mailing-info-strip">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>Состав аудитории повторно проверяется в момент запуска: заблокированные и недоступные получатели автоматически исключаются.</span>
    </div>

    <div class="mailing-list-head">
        <div><h2>Сохранённые аудитории</h2><span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['аудитория', 'аудитории', 'аудиторий']) ?></span></div>
        <span class="mailing-list-head__hint"><i class="fa-solid fa-filter" aria-hidden="true"></i> Поиск — в панели фильтров</span>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => "{items}\n{pager}",
        'filterRowOptions' => ['class' => 'd-none'],
        'options' => ['class' => 'mailing-grid'],
        'tableOptions' => ['class' => 'table mailing-table'],
        'bordered' => false,
        'striped' => false,
        'hover' => true,
        'emptyText' => 'Сохранённых аудиторий пока нет.',
        'columns' => [
            [
                'attribute' => 'name',
                'format' => 'raw',
                'value' => static function (TelegramRecipients $model) {
                    $isAutomatic = array_key_exists($model->name, TelegramConstructor::getLkLanguagesArr());
                    return Html::a(
                        '<strong>' . Html::encode($model->name) . '</strong><span>' . ($isAutomatic ? 'Обновляется по языку профиля' : 'Ручная выборка') . '</span>',
                        ['view', 'id' => $model->id],
                        ['class' => 'mailing-title-link']
                    );
                },
            ],
            [
                'label' => 'Получателей',
                'format' => 'raw',
                'value' => static fn(TelegramRecipients $model) => Html::tag('strong', Yii::$app->formatter->asInteger($model->getResolvedQuantity()), ['class' => 'mailing-quantity']),
            ],
            [
                'label' => 'Используется',
                'value' => static fn(TelegramRecipients $model) => $model->getUsageLabel(),
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y, H:i'],
                'contentOptions' => ['class' => 'mailing-table__date'],
            ],
            [
                'class' => ActionColumn::class,
                'template' => '{view} {update} {delete}',
                'contentOptions' => ['class' => 'mailing-row-actions'],
                'urlCreator' => static fn($action, $model) => Url::toRoute([$action, 'id' => $model->id]),
                'buttons' => [
                    'view' => static fn($url) => Html::a('<i class="fa-solid fa-eye" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Открыть', 'aria-label' => 'Открыть аудиторию']),
                    'update' => static fn($url) => Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Редактировать', 'aria-label' => 'Редактировать аудиторию']),
                    'delete' => static function ($url, TelegramRecipients $model) {
                        if ($model->getUsageCount() > 0) {
                            return Html::tag('span', '<i class="fa-solid fa-lock" aria-hidden="true"></i>', [
                                'class' => 'ds-btn ds-btn--icon ds-btn--ghost is-disabled',
                                'title' => 'Аудитория используется и не может быть удалена',
                                'aria-label' => 'Удаление недоступно: аудитория используется',
                                'aria-disabled' => 'true',
                            ]);
                        }

                        return Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost mailing-danger-action', 'title' => 'Удалить', 'aria-label' => 'Удалить аудиторию', 'data' => ['confirm' => 'Удалить аудиторию?', 'method' => 'post']]);
                    },
                ],
            ],
        ],
    ]) ?>
</div>

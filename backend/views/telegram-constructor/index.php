<?php

use backend\components\AccessibleKartikGridView as GridView;
use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use common\helpers\HStrings;
use yii\bootstrap5\Html;
use yii\grid\ActionColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var backend\models\TelegramConstructorSearch $searchModel */
/** @var int $countTelegramUsers */
/** @var int $countVkUsers */

$this->title = 'Рассылки сообщений';
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
$isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
$totalCount = $dataProvider->getTotalCount();
?>
<div class="mailing-page mailing-campaigns-page">
    <?= $this->render('_section_nav') ?>
    <?= \frontend\widgets\Alert::widget() ?>

    <header class="mailing-page-head">
        <div>
            <span class="mailing-page-head__eyebrow">Коммуникации</span>
            <h1>Рассылки</h1>
            <p>Создавайте черновики, проверяйте аудиторию и содержимое, затем запускайте отправку.</p>
        </div>
        <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новая рассылка', ['create'], ['class' => 'ds-btn ds-btn--primary']) ?>
    </header>

    <section class="mailing-metrics" aria-label="Доступные получатели">
        <article class="mailing-metric">
            <span class="mailing-metric__icon mailing-metric__icon--telegram"><i class="fa-brands fa-telegram" aria-hidden="true"></i></span>
            <span class="mailing-metric__body">
                <strong><?= Yii::$app->formatter->asInteger($countTelegramUsers) ?></strong>
                <span>доступно в Telegram</span>
            </span>
            <?php if ($isAdmin): ?>
                <?= Html::a('<i class="fa-solid fa-rotate" aria-hidden="true"></i>', ['update-telegram-audience'], [
                    'class' => 'ds-btn ds-btn--icon ds-btn--ghost',
                    'title' => 'Проверить доступность Telegram-получателей',
                    'aria-label' => 'Обновить аудиторию Telegram',
                    'data' => ['confirm' => 'Запустить проверку доступности Telegram-получателей?', 'method' => 'post'],
                ]) ?>
            <?php endif; ?>
        </article>
        <article class="mailing-metric">
            <span class="mailing-metric__icon mailing-metric__icon--vk"><i class="fa-brands fa-vk" aria-hidden="true"></i></span>
            <span class="mailing-metric__body">
                <strong><?= Yii::$app->formatter->asInteger($countVkUsers) ?></strong>
                <span>доступно во ВКонтакте</span>
            </span>
            <?php if ($isAdmin): ?>
                <?= Html::a('<i class="fa-solid fa-rotate" aria-hidden="true"></i>', ['update-vk-audience'], [
                    'class' => 'ds-btn ds-btn--icon ds-btn--ghost',
                    'title' => 'Обновить аудиторию ВКонтакте',
                    'aria-label' => 'Обновить аудиторию ВКонтакте',
                    'data' => ['confirm' => 'Запустить обновление аудитории ВКонтакте? Это может занять время.', 'method' => 'post'],
                ]) ?>
            <?php endif; ?>
        </article>
        <div class="mailing-metrics__links">
            <?= Html::a('<i class="fa-solid fa-message" aria-hidden="true"></i> Шаблоны', ['/telegram-constructor-message/index'], ['class' => 'mailing-quiet-link']) ?>
            <?= Html::a('<i class="fa-solid fa-users" aria-hidden="true"></i> Аудитории', ['/telegram-recipients/index'], ['class' => 'mailing-quiet-link']) ?>
        </div>
    </section>

    <div class="mailing-list-head">
        <div>
            <h2>История и черновики</h2>
            <span><?= Yii::$app->formatter->asInteger($totalCount) ?> <?= HStrings::pluralForm($totalCount, ['запись', 'записи', 'записей']) ?></span>
        </div>
        <span class="mailing-list-head__hint"><i class="fa-solid fa-filter" aria-hidden="true"></i> Фильтры — в панели справа</span>
    </div>

    <div class="mailing-desktop-list">
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
            'emptyText' => 'Рассылок пока нет. Создайте первый черновик.',
            'columns' => [
                [
                    'attribute' => 'title',
                    'format' => 'raw',
                    'value' => static function (TelegramConstructor $model) {
                        return Html::a(
                            '<strong>' . Html::encode($model->title) . '</strong><span>#' . (int)$model->id . '</span>',
                            ['view', 'id' => $model->id],
                            ['class' => 'mailing-title-link']
                        );
                    },
                ],
                [
                    'attribute' => 'bot_id',
                    'format' => 'raw',
                    'value' => static function (TelegramConstructor $model) {
                        $isVk = $model->bot_id === TelegramConstructor::VK_GROUP;
                        $icon = $isVk ? 'fa-brands fa-vk' : 'fa-brands fa-telegram';
                        return Html::tag('span', '<i class="' . $icon . '" aria-hidden="true"></i> ' . Html::encode(ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id, 'Неизвестно')), ['class' => 'mailing-channel']);
                    },
                ],
                [
                    'attribute' => 'audience_id',
                    'value' => static fn(TelegramConstructor $model) => TelegramConstructor::getAudienceName($model->audience_id),
                ],
                [
                    'label' => 'Шаблон',
                    'format' => 'raw',
                    'value' => static function (TelegramConstructor $model) {
                        return $model->telegramConstructorMessage
                            ? Html::a(Html::encode($model->telegramConstructorMessage->title), ['/telegram-constructor-message/view', 'id' => $model->telegramConstructorMessage->id], ['class' => 'mailing-table-link'])
                            : Html::tag('span', 'Шаблон удалён', ['class' => 'mailing-inline-error']);
                    },
                ],
                [
                    'attribute' => 'status',
                    'format' => 'raw',
                    'value' => static function (TelegramConstructor $model) {
                        $classes = [
                            TelegramConstructor::STATUS_NEW => 'is-draft',
                            TelegramConstructor::STATUS_IN_PROGRESS => 'is-progress',
                            TelegramConstructor::STATUS_SUCCESS => 'is-success',
                            TelegramConstructor::STATUS_ERROR => 'is-error',
                        ];
                        return Html::tag('span', Html::encode(ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status, 'Неизвестно')), ['class' => 'mailing-status ' . ($classes[$model->status] ?? '')]);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'format' => ['datetime', 'php:d.m.Y, H:i'],
                    'contentOptions' => ['class' => 'mailing-table__date'],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{view} {play} {update} {delete}',
                    'contentOptions' => ['class' => 'mailing-row-actions'],
                    'urlCreator' => static fn($action, $model) => Url::toRoute([$action, 'id' => $model->id]),
                    'buttons' => [
                        'view' => static fn($url) => Html::a('<i class="fa-solid fa-eye" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Открыть', 'aria-label' => 'Открыть рассылку']),
                        'play' => static function ($url, TelegramConstructor $model) {
                            if ($model->status !== TelegramConstructor::STATUS_NEW || !Yii::$app->user->can(Role::ROLE_ADMIN)) return '';
                            return Html::a('<i class="fa-solid fa-play" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--success', 'title' => 'Запустить', 'aria-label' => 'Запустить рассылку', 'data' => ['confirm' => 'Запустить рассылку? После запуска изменить или повторно отправить её нельзя.', 'method' => 'post']]);
                        },
                        'update' => static fn($url, TelegramConstructor $model) => $model->status === TelegramConstructor::STATUS_NEW ? Html::a('<i class="fa-solid fa-pen" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost', 'title' => 'Редактировать', 'aria-label' => 'Редактировать рассылку']) : '',
                        'delete' => static function ($url, TelegramConstructor $model) {
                            if ($model->status !== TelegramConstructor::STATUS_NEW || !Yii::$app->user->can(Role::ROLE_ADMIN)) return '';
                            return Html::a('<i class="fa-solid fa-trash" aria-hidden="true"></i>', $url, ['class' => 'ds-btn ds-btn--icon ds-btn--ghost mailing-danger-action', 'title' => 'Удалить черновик', 'aria-label' => 'Удалить черновик', 'data' => ['confirm' => 'Удалить черновик рассылки?', 'method' => 'post']]);
                        },
                    ],
                ],
            ],
        ]) ?>
    </div>

    <div class="mailing-mobile-list">
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_telegram_constructor_card',
            'layout' => "{items}\n<div class=\"mailing-mobile-pager\">{pager}</div>",
            'itemOptions' => ['tag' => false],
            'options' => ['class' => 'mailing-campaign-cards'],
            'emptyText' => 'Рассылок пока нет. Создайте первый черновик.',
        ]) ?>
    </div>
</div>

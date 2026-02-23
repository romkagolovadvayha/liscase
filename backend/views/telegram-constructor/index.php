<?php

use backend\models\TelegramConstructor;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\components\helpers\Role;
use yii\grid\ActionColumn;
use yii\widgets\ListView;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $countTelegramUsers */
/** @var $countVkUsers */
/** @var $model TelegramConstructor */

$this->title = Yii::t('common', 'Конструктор сообщений телеграм бота');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="tc-index-page w-full">
    <?= \frontend\widgets\Alert::widget() ?>

    <!-- Статистика и действия (без карточек) -->
    <div class="tc-index-top p-4 lg:p-6 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="tc-index-stats flex flex-wrap items-stretch gap-4 mb-4">
            <div class="tc-index-stat flex items-center gap-3">
                <div class="tc-index-stat-body">
                    <span class="tc-index-stat-value"><?= Yii::$app->formatter->asInteger($countTelegramUsers) ?></span>
                    <span class="tc-index-stat-label">Telegram получателей</span>
                </div>
                <?= Html::a('<i class="fas fa-sync-alt"></i> <span class="tc-btn-label">Обновить</span>', ['update-telegram-audience'], [
                    'class' => 'ds-btn ds-btn--primary ds-btn--sm flex-shrink-0',
                    'title' => 'Обновить счётчик Telegram получателей',
                    'data' => ['confirm' => 'Обновить счётчик Telegram получателей?', 'method' => 'post'],
                ]) ?>
            </div>
            <div class="tc-index-stat flex items-center gap-3">
                <div class="tc-index-stat-body">
                    <span class="tc-index-stat-value"><?= Yii::$app->formatter->asInteger($countVkUsers) ?></span>
                    <span class="tc-index-stat-label">ВКонтакте получателей</span>
                </div>
                <?= Html::a('<i class="fas fa-sync-alt"></i> <span class="tc-btn-label">Обновить</span>', ['update-vk-audience'], [
                    'class' => 'ds-btn ds-btn--primary ds-btn--sm flex-shrink-0',
                    'title' => 'Обновить аудиторию ВКонтакте',
                    'data' => ['confirm' => 'Обновить аудиторию ВКонтакте? Может занять время.', 'method' => 'post'],
                ]) ?>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <?= Html::a('<i class="fas fa-plus"></i> ' . Yii::t('common', 'Создать рассылку'), ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
            <?= Html::a('<i class="fas fa-envelope"></i> ' . Yii::t('common', 'Сообщения для рассылок'), ['/telegram-constructor-message/index'], ['class' => 'ds-btn ds-btn--info']) ?>
        </div>
    </div>

    <!-- Десктоп: таблица -->
    <div class="tc-index-desktop">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
            'options'      => ['class' => 'admin-grid-view-dark tc-grid-view'],
            'layout'       => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered'     => false,
            'striped'      => false,
            'hover'        => true,
            'columns'      => [
                [
                    'attribute' => 'id',
                    'options'   => ['width' => '80'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'title',
                    'format'    => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value'     => function ($model) {
                        return Html::a(Html::encode($model->title), ['view', 'id' => $model->id], ['class' => 'text-white hover:underline', 'style' => 'text-decoration: none;']);
                    },
                ],
                [
                    'attribute' => 'bot_id',
                    'filter'    => ArrayHelper::merge(['' => 'Все'], TelegramConstructor::getBotList()),
                    'format'    => 'raw',
                    'options'   => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value'     => function ($model) {
                        $botName = ArrayHelper::getValue(TelegramConstructor::getBotList(), $model->bot_id);
                        $badgeClass = $model->bot_id == TelegramConstructor::VK_GROUP ? 'ds-badge--info' : 'ds-badge--primary';
                        return Html::tag('span', Html::encode($botName), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'audience_id',
                    'filter'    => ArrayHelper::merge(['' => 'Все'], TelegramConstructor::getAudienceList()),
                    'format'    => 'raw',
                    'options'   => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value'     => function ($model) {
                        $audienceName = ArrayHelper::getValue(TelegramConstructor::getAudienceList(), $model->audience_id);
                        return Html::tag('span', Html::encode($audienceName), ['class' => 'ds-badge ds-badge--primary']);
                    },
                ],
                [
                    'attribute' => 'message',
                    'label'    => Yii::t('common', 'Сообщение'),
                    'format'   => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value'    => function (\backend\models\TelegramConstructorSearch $model) {
                        if (empty($model->telegramConstructorMessage)) {
                            return Html::tag('span', 'Удалено', ['class' => 'ds-badge ds-badge--danger']);
                        }
                        return Html::a(
                            Html::encode($model->telegramConstructorMessage->title),
                            ['/telegram-constructor-message/update', 'id' => $model->telegramConstructorMessage->id],
                            ['class' => 'text-white hover:underline', 'style' => 'text-decoration: none;']
                        );
                    },
                ],
                [
                    'attribute'      => 'status',
                    'filter'        => ArrayHelper::merge(['all' => 'Все'], TelegramConstructor::getStatusList()),
                    'filterType'    => GridView::FILTER_SELECT2,
                    'options'       => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'format'        => 'raw',
                    'value'         => function ($model) {
                        $statusName = ArrayHelper::getValue(TelegramConstructor::getStatusList(), $model->status);
                        $badgeClass = 'ds-badge--primary';
                        if ($model->status == TelegramConstructor::STATUS_SUCCESS) $badgeClass = 'ds-badge--success';
                        elseif ($model->status == TelegramConstructor::STATUS_ERROR) $badgeClass = 'ds-badge--danger';
                        elseif ($model->status == TelegramConstructor::STATUS_IN_PROGRESS) $badgeClass = 'ds-badge--warning';
                        return Html::tag('span', Html::encode($statusName), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'class'     => \common\components\grid\DateColumn::class,
                    'options'   => ['width' => '160'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class'       => ActionColumn::class,
                    'template'    => '{play} {update} {delete}',
                    'options'     => ['width' => '130'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator'  => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                    'buttons'     => [
                        'play' => function ($url, $model) {
                            if ($model->status !== TelegramConstructor::STATUS_NEW) return '';
                            if (!Yii::$app->user->can(Role::ROLE_ADMIN)) {
                                return Html::a('<i class="fas fa-play"></i>', '#', ['class' => 'ds-btn ds-btn--secondary ds-btn--sm disabled', 'title' => Yii::t('common', 'Недостаточно прав'), 'aria-disabled' => 'true']);
                            }
                            return Html::a('<i class="fas fa-play"></i>', $url, [
                                'class' => 'ds-btn ds-btn--success ds-btn--sm',
                                'title' => Yii::t('common', 'Запустить рассылку'),
                                'data'  => ['confirm' => Yii::t('common', 'Запустить рассылку?'), 'method' => 'post'],
                            ]);
                        },
                        'update' => function ($url, $model) {
                            if ($model->status !== TelegramConstructor::STATUS_NEW) return '';
                            return Html::a('<i class="fas fa-pencil-alt"></i>', $url, ['class' => 'ds-btn ds-btn--primary ds-btn--sm', 'title' => Yii::t('common', 'Редактировать')]);
                        },
                        'delete' => function ($url, $model) {
                            if ($model->status !== TelegramConstructor::STATUS_NEW) return '';
                            if (!Yii::$app->user->can(Role::ROLE_ADMIN)) {
                                return Html::a('<i class="fas fa-trash"></i>', '#', ['class' => 'ds-btn ds-btn--secondary ds-btn--sm disabled', 'title' => Yii::t('common', 'Недостаточно прав'), 'aria-disabled' => 'true']);
                            }
                            return Html::a('<i class="fas fa-trash"></i>', $url, [
                                'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                'title' => Yii::t('common', 'Удалить'),
                                'data'  => ['confirm' => Yii::t('common', 'Удалить рассылку?'), 'method' => 'post'],
                            ]);
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>

    <!-- Мобилка: карточки -->
    <div class="tc-index-mobile">
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView'     => '_telegram_constructor_card',
            'layout'       => "{items}\n<div class=\"tc-index-mobile-pager\">{pager}</div>",
            'itemOptions'  => ['class' => 'tc-index-card-wrap', 'tag' => 'div'],
            'options'      => ['class' => 'tc-index-cards', 'tag' => 'div'],
        ]) ?>
    </div>
</div>

<style>
.tc-grid-view { background: hsl(0 0% 10% / 1) !important; }
.tc-grid-view .table, .tc-grid-view table, .tc-grid-view .kv-grid-table { background: hsl(0 0% 10% / 1) !important; border-collapse: collapse; color: white !important; border: none !important; }
.tc-grid-view .table thead th, .tc-grid-view table thead th, .tc-grid-view .kv-grid-table thead th { background: hsl(0 0% 20.4% / 1) !important; color: hsl(0 0% 70% / 1) !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-grid-view .table tbody td, .tc-grid-view table tbody td, .tc-grid-view .kv-grid-table tbody td { background: transparent !important; color: white !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tc-grid-view .table tbody tr:hover { background: hsl(0 0% 15% / 1) !important; }
.tc-grid-view .pagination, .tc-grid-view .kv-panel-pager { background: hsl(0 0% 10% / 1) !important; color: white !important; }
.tc-grid-view .pagination .page-link { background: hsl(0 0% 20.4% / 1) !important; color: white !important; border-color: hsl(0 0% 15.3% / 1) !important; }
.tc-index-mobile { display: none; }
@media (max-width: 991px) {
    .tc-index-desktop { display: none !important; }
    .tc-index-mobile { display: block; padding: 12px; }
}
.tc-index-cards { margin: 0; padding: 0; list-style: none; }
.tc-index-card-wrap { margin-bottom: 10px; }
.tc-index-card { padding: 12px; background: hsl(0 0% 15% / 1); border: 1px solid hsl(0 0% 20% / 1); border-radius: 8px; }
.tc-index-card__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
.tc-index-card__title { color: white; text-decoration: none; font-weight: 600; }
.tc-index-card__title:hover { text-decoration: underline; }
.tc-index-card__meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: hsl(0 0% 65%); }
.tc-index-card__row { margin-bottom: 6px; font-size: 0.875rem; }
.tc-index-card__label { color: hsl(0 0% 60%); margin-right: 6px; }
.tc-index-card__value .tc-index-card__link { color: white; text-decoration: none; }
.tc-index-card__value .tc-index-card__link:hover { text-decoration: underline; }
.tc-index-card__date { font-size: 0.75rem; color: hsl(0 0% 55%); margin-bottom: 8px; }
.tc-index-card__actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; padding-top: 8px; border-top: 1px solid hsl(0 0% 22% / 1); }
.tc-index-mobile-pager { margin-top: 12px; }
.tc-index-stat-body { display: flex; flex-direction: column; gap: 0.125rem; }
.tc-index-stat-value { display: block; font-size: 1.5rem; font-weight: 600; color: #fff; line-height: 1.2; }
.tc-index-stat-label { display: block; font-size: 0.875rem; color: hsl(0 0% 65%); }
.tc-index-top .ds-btn .tc-btn-label { margin-left: 0.35rem; }
.tc-grid-view .ds-btn.disabled { opacity: 0.65; pointer-events: none; cursor: not-allowed; }
.tc-index-card__actions .ds-btn { display: inline-flex; align-items: center; gap: 0.35rem; }
</style>

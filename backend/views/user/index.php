<?php

use common\components\helpers\Role;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\grid\ActionColumn;
use yii\widgets\ListView;
use common\models\user\UserSearch;
use common\models\user\User;

$this->title = Yii::t('common', 'Пользователи');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="user-index-page w-full">
    <!-- Десктоп: таблица -->
    <div class="user-index-desktop">
        <div class="user-index-table-wrap">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => [
                'class' => 'table-auto w-full text-sm user-table-dark user-index-table',
            ],
            'options' => [
                'class' => 'user-grid-view',
            ],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'attribute' => 'id',
                    'options' => ['width' => '60'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'format' => 'raw',
                    'label' => '',
                    'options' => ['width' => '48px'],
                    'headerOptions' => ['class' => $headerCellClass . ' user-index-avatar-cell'],
                    'contentOptions' => ['class' => $bodyCellClass . ' user-index-avatar-cell'],
                    'value' => function (UserSearch $model) {
                        $avatar = $model->getAvatar();
                        if (empty($avatar)) {
                            return '<span class="user-index-avatar-placeholder">—</span>';
                        }
                        return Html::tag('div', Html::img($avatar, [
                            'width' => 32,
                            'height' => 32,
                            'style' => 'border-radius: 50%; object-fit: cover; display: block;',
                            'loading' => 'lazy',
                            'alt' => Html::encode($model->username ?? ''),
                        ]), ['class' => 'user-index-avatar-wrap']);
                    },
                ],
                [
                    'attribute' => 'username',
                    'label' => 'Ник',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (UserSearch $model) {
                        $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                        $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                        if (!$isAdmin && !$isModerator) {
                            return Html::encode($model->username);
                        }
                        $url = Url::to('/profile/' . $model->id);
                        return Html::a(Html::encode($model->username), $url, [
                            'class' => 'text-white hover:underline',
                            'style' => 'text-decoration: none;',
                        ]);
                    },
                ],
                [
                    'attribute' => 'steam_id',
                    'options' => ['width' => '140'],
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (UserSearch $model) {
                        return Html::a($model->steam_id, 'https://steamcommunity.com/profiles/' . $model->steam_id, [
                            'target' => '_blank',
                            'class' => 'text-white hover:underline',
                            'style' => 'text-decoration: none;',
                        ]);
                    },
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '120'],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Все'], User::getStatusList()),
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (UserSearch $model) {
                        $status = ArrayHelper::getValue(User::getStatusList(), $model->status);
                        $badgeClass = $model->status == User::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'options' => ['width' => '200'],
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'last_visit_server_at',
                    'options' => ['width' => '200'],
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{switch}',
                    'options' => ['width' => '90'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute(['/user/switch-identity', 'id' => $model->id]);
                    },
                    'buttons' => [
                        'switch' => function ($url, $model) {
                            $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                            $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                            if ($model->status != UserSearch::STATUS_ACTIVE || (!$isAdmin && !$isModerator) || $model->isSwitchIdentityForbidden()) {
                                return null;
                            }
                            return Html::a('Войти', $url, [
                                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
                                'title' => Yii::t('common', 'Перейти в личный кабинет'),
                            ]);
                        },
                    ],
                ],
            ],
        ]); ?>
        </div>
    </div>

    <!-- Мобилка: карточки пользователей -->
    <div class="user-index-mobile">
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_user_card',
            'layout' => "{items}\n<div class=\"user-index-mobile-pager\">{pager}</div>",
            'itemOptions' => ['class' => 'user-index-card-wrap', 'tag' => 'div'],
            'options' => ['class' => 'user-index-cards', 'tag' => 'div'],
        ]) ?>
    </div>
</div>

<style>
.content-no-padding {
    padding: 0 !important;
}

.user-grid-view {
    background: hsl(0 0% 10% / 1) !important;
}

.user-grid-view .table,
.user-grid-view table,
.user-grid-view .kv-grid-table,
.user-grid-view .user-table-dark,
.user-grid-view .table-striped,
.user-grid-view .table-bordered,
.user-grid-view .table-hover,
.user-grid-view .table-sm {
    background: hsl(0 0% 10% / 1) !important;
    border-collapse: collapse;
    width: 100%;
    color: white !important;
    border: none !important;
    border-spacing: 0;
}

.user-grid-view .table thead th,
.user-grid-view table thead th,
.user-grid-view .kv-grid-table thead th,
.user-grid-view .table thead td,
.user-grid-view table thead td {
    background: hsl(0 0% 20.4% / 1) !important;
    color: hsl(0 0% 70% / 1) !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important;
    font-weight: 600 !important;
}

.user-grid-view .table tbody tr,
.user-grid-view table tbody tr,
.user-grid-view .kv-grid-table tbody tr {
    background: hsl(0 0% 10% / 1) !important;
    color: white !important;
    border: none !important;
}

.user-grid-view .table tbody tr:hover,
.user-grid-view table tbody tr:hover,
.user-grid-view .kv-grid-table tbody tr:hover,
.user-grid-view .table-hover tbody tr:hover {
    background: hsl(0 0% 15% / 1) !important;
}

.user-grid-view .table tbody td,
.user-grid-view table tbody td,
.user-grid-view .kv-grid-table tbody td {
    background: transparent !important;
    color: white !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important;
}

.user-grid-view .table tbody tr:last-child td,
.user-grid-view table tbody tr:last-child td,
.user-grid-view .kv-grid-table tbody tr:last-child td {
    border-bottom: none;
}

.user-grid-view .table-striped tbody tr:nth-of-type(odd) {
    background: hsl(0 0% 10% / 1) !important;
}

.user-grid-view .table-striped tbody tr:nth-of-type(even) {
    background: hsl(0 0% 12% / 1) !important;
}

.user-grid-view .pagination,
.user-grid-view .kv-panel-pager {
    background: hsl(0 0% 10% / 1) !important;
    color: white !important;
}

.user-grid-view .pagination .page-link {
    background: hsl(0 0% 20.4% / 1) !important;
    color: white !important;
    border-color: hsl(0 0% 15.3% / 1) !important;
}

.user-grid-view .pagination .page-link:hover {
    background: hsl(0 0% 25% / 1) !important;
}

.user-grid-view .pagination .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}

/* Колонка аватара: фиксированная ширина, иначе перекрывается классом w0 (id грида) */
.user-grid-view .user-index-avatar-cell,
.user-grid-view th.user-index-avatar-cell,
.user-grid-view td.user-index-avatar-cell {
    min-width: 48px !important;
    width: 48px !important;
    max-width: 48px !important;
    box-sizing: border-box;
}
.user-grid-view .user-index-avatar-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-grid-view .user-index-avatar-placeholder {
    color: hsl(0 0% 50%);
    font-size: 0.875rem;
}

/* Мобилка: показываем карточки, скрываем таблицу */
.user-index-mobile {
    display: none;
}
@media (max-width: 991px) {
    .user-index-desktop {
        display: none !important;
    }
    .user-index-mobile {
        display: block;
        padding: 12px;
    }
}

/* Карточка пользователя (мобилка) */
.user-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
}
.user-index-card-wrap {
    margin-bottom: 10px;
}
.user-index-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: hsl(0 0% 15% / 1);
    border-radius: 10px;
    border: 1px solid hsl(0 0% 20% / 1);
    min-height: 72px;
    box-sizing: border-box;
}
.user-index-card__avatar {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    background: hsl(0 0% 22% / 1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-index-card__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.user-index-card__avatar-placeholder {
    color: hsl(0 0% 50%);
    font-size: 0.875rem;
}
.user-index-card__body {
    flex: 1;
    min-width: 0;
}
.user-index-card__row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.user-index-card__name {
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.user-index-card__name:hover {
    text-decoration: underline;
}
.user-index-card__meta {
    font-size: 12px;
    color: hsl(0 0% 60%);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.user-index-card__id {
    color: hsl(0 0% 55%);
}
.user-index-card__steam {
    color: hsl(0 0% 65%);
    text-decoration: none;
}
.user-index-card__steam:hover {
    text-decoration: underline;
}
.user-index-card__date {
    font-size: 11px;
    color: hsl(0 0% 50%);
    margin-top: 2px;
}
.user-index-card__action {
    flex-shrink: 0;
}
.user-index-card__action .ds-btn {
    min-height: 44px;
    padding: 10px 14px;
}

.user-index-mobile-pager {
    margin-top: 16px;
    padding: 12px 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
}
.user-index-mobile-pager .pagination {
    margin: 0;
}
.user-index-mobile-pager .page-link {
    min-width: 44px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: hsl(0 0% 20% / 1) !important;
    color: #fff !important;
    border-color: hsl(0 0% 15% / 1) !important;
}
.user-index-mobile-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}

/* Десктоп: таблица (мобильные стили убраны, т.к. таблица скрыта на мобилке) */
@media (min-width: 992px) {
    .user-index-page {
        padding: 0;
    }
}
</style>

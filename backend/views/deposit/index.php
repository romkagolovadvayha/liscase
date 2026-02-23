<?php

use common\models\invoice\Deposit;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\widgets\ListView;
use backend\models\DepositsSearch;
use common\components\helpers\Role;

/** @var yii\web\View $this */
/** @var backend\models\DepositsSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Депозиты');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="deposit-index-page w-full">
    <!-- Десктоп: таблица -->
    <div class="deposit-index-desktop">
    <div class="w-full">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
            'options' => ['class' => 'admin-grid-view-dark'],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'attribute' => 'id',
                    'format' => 'raw',
                    'options' => ['width' => '80'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'username',
                    'format' => 'raw',
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Deposit $model) {
                        $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                        $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                        if (!$model->user) {
                            return '—';
                        }
                        if (!$isAdmin && !$isModerator) {
                            return Html::encode($model->user->username);
                        }
                        return Html::a(Html::encode($model->user->username), ['/user/profile', 'userId' => $model->user->id], ['class' => 'text-blue-400 hover:underline']);
                    },
                ],
                [
                    'attribute' => 'steam_id',
                    'options' => ['width' => '120'],
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Deposit $model) {
                        if (!$model->user) {
                            return '—';
                        }
                        return Html::a(Html::encode($model->user->steam_id), 'https://steamcommunity.com/profiles/' . $model->user->steam_id, ['target' => '_blank', 'class' => 'text-blue-400 hover:underline']);
                    },
                ],
                [
                    'attribute' => 'payment_type',
                    'options' => ['width' => '180'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Deposit $model) {
                        return ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type, $model->payment_type);
                    },
                ],
                [
                    'attribute' => 'amount',
                    'options' => ['width' => '100'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'payment_id',
                    'format' => 'ntext',
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' max-w-[180px] truncate'],
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '160'],
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Deposit $model) {
                        $status = ArrayHelper::getValue(Deposit::getStatusList(), $model->status, '');
                        $badgeClass = $model->status == Deposit::STATUS_SUCCESS
                            ? 'ds-badge--success'
                            : ($model->status == Deposit::STATUS_WAIT_CONFIRM ? 'ds-badge--warning' : 'ds-badge--danger');
                        $out = Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                        if ($model->status == Deposit::STATUS_WAIT_CONFIRM && !empty($model->payment_id)) {
                            $checkResult = $model->debugCheck();
                            $resultName = $checkResult === 'partially-paid' ? Yii::t('common', 'Частично оплачен') : $checkResult;
                            $out .= '<br/><small class="text-gray-500 text-xs">' . Html::encode($resultName) . '</small>';
                        }
                        return $out;
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'options' => ['width' => '160'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'format' => ['date', 'php:Y-m-d H:i'],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{accept} {view} {update}',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'buttons' => [
                        'accept' => function ($url, Deposit $model) {
                            if ($model->status == Deposit::STATUS_SUCCESS) {
                                return '';
                            }
                            return Html::a(
                                '<i class="fas fa-check"></i>',
                                $url,
                                [
                                    'class' => 'ds-btn ds-btn--success ds-btn--sm',
                                    'title' => Yii::t('common', 'Принять депозит'),
                                    'data-confirm' => Yii::t('common', 'Вы уверены, что хотите принять этот депозит?'),
                                    'data-method' => 'post',
                                ]
                            );
                        },
                    ],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
    </div>

    <!-- Мобилка: карточки депозитов -->
    <div class="deposit-index-mobile">
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_deposit_card',
            'layout' => "{items}\n<div class=\"deposit-index-mobile-pager\">{pager}</div>",
            'itemOptions' => ['class' => 'deposit-index-card-wrap', 'tag' => 'div'],
            'options' => ['class' => 'deposit-index-cards', 'tag' => 'div'],
        ]) ?>
    </div>
</div>

<style>
.deposit-index-mobile { display: none; }
@media (max-width: 991px) {
    .deposit-index-desktop { display: none !important; }
    .deposit-index-mobile { display: block; padding: 12px; }
}
.deposit-index-cards { margin: 0; padding: 0; list-style: none; }
.deposit-index-card-wrap { margin-bottom: 12px; }
.deposit-index-card {
    padding: 14px;
    background: hsl(0 0% 15% / 1);
    border-radius: 10px;
    border: 1px solid hsl(0 0% 20% / 1);
    box-sizing: border-box;
}
.deposit-index-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}
.deposit-index-card__id { font-weight: 600; color: hsl(0 0% 70%); font-size: 0.875rem; }
.deposit-index-card__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    font-size: 13px;
}
.deposit-index-card__label { color: hsl(0 0% 55%); flex-shrink: 0; }
.deposit-index-card__value { color: hsl(0 0% 88%); word-break: break-all; }
.deposit-index-card__amount { color: #fff; font-weight: 600; }
.deposit-index-card__link { color: hsl(200 70% 60%); text-decoration: none; }
.deposit-index-card__link:hover { text-decoration: underline; }
.deposit-index-card__debug { font-size: 12px; color: hsl(0 0% 55%); }
.deposit-index-card__date { font-size: 12px; color: hsl(0 0% 50%); margin-top: 8px; margin-bottom: 10px; }
.deposit-index-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid hsl(0 0% 20% / 1);
}
.deposit-index-card__actions .ds-btn { min-height: 44px; padding: 10px 14px; }
.deposit-index-mobile-pager {
    margin-top: 16px;
    padding: 12px 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
}
.deposit-index-mobile-pager .pagination { margin: 0; }
.deposit-index-mobile-pager .page-link {
    min-width: 44px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: hsl(0 0% 20% / 1) !important;
    color: #fff !important;
    border-color: hsl(0 0% 15% / 1) !important;
}
.deposit-index-mobile-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}
</style>

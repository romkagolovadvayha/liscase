<?php

use common\models\invoice\Deposit;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;
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

<?php

use common\models\invoice\Deposit;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;
use common\components\helpers\Role;

/** @var yii\web\View $this */
/** @var backend\models\DepositsSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Депозиты';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="deposit-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '60']
            ],
            [
                'format'    => 'raw',
                'attribute' => 'username',
                'value'          => function (Deposit $model) {
                    $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                    $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                    if (!$isAdmin && !$isModerator) {
                        return Html::encode($model->user->username);
                    }
                    $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->user->id]);
                    return Html::a(Html::encode($model->user->username), $url, [
                        'class' => 'ds-text--primary',
                        'style' => 'text-decoration: none;'
                    ]);
                },
            ],
            [
                'attribute' => 'steam_id',
                'options'   => ['width' => '100'],
                'format'    => 'raw',
                'value'          => function (Deposit $model) {
                    return Html::a($model->user->steam_id, 'https://steamcommunity.com/profiles/' . $model->user->steam_id, [
                        'target' => '_blank',
                        'class' => 'ds-text--primary',
                        'style' => 'text-decoration: none;'
                    ]);
                },
            ],
            [
                'attribute' => 'payment_type',
                'options'   => ['width' => '250'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'],  Deposit::getTypeList()),
                'value'     => function (Deposit $model) {
                    return ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type);
                },
            ],
            [
                'attribute' => 'amount',
                'options'   => ['width' => '90']
            ],
            'payment_id:ntext',
            [
                'attribute' => 'status',
                'format'    => 'raw',
                'options'   => ['width' => '200'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'],  Deposit::getStatusList()),
                'value'     => function (Deposit $model) {
                    $status = ArrayHelper::getValue(Deposit::getStatusList(), $model->status);
                    $badgeClass = $model->status == Deposit::STATUS_SUCCESS 
                        ? 'ds-badge--success' 
                        : ($model->status == Deposit::STATUS_WAIT_CONFIRM 
                            ? 'ds-badge--warning' 
                            : 'ds-badge--danger');
                    
                    $result = '';
                    if ($model->status == Deposit::STATUS_WAIT_CONFIRM && !empty($model->payment_id)) {
                        $checkResult = $model->debugCheck();
                        $resultName = $checkResult;
                        if ($resultName == 'partially-paid') {
                            $resultName = "Частично оплачен";
                        }
                        $result = "<br/><small style='color: #888;'>Статус в платежной системе: {$resultName}</small>";
                    }
                    
                    return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]) . $result;
                },
            ],
            [
                'attribute' => 'created_at',
                'options'   => ['width' => '200']
            ],
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{accept} {update}',
                'options'  => [
                    'width' => '150'
                ],
                'buttons'  => [
                    'accept' => function ($url, Deposit $model) {
                        if ($model->status == Deposit::STATUS_SUCCESS) {
                            return '';
                        }
                        return Html::a(
                            '<i class="fas fa-check"></i>',
                            ['accept', 'id' => $model->id],
                            [
                                'class' => 'ds-btn ds-btn--success ds-btn--sm',
                                'title' => 'Принять депозит',
                                'data-confirm' => 'Вы уверены, что хотите принять этот депозит?',
                                'data-method' => 'post',
                            ]
                        );
                    },
                ],
            ],
        ],
    ]); ?>
        </div>
    </div>
</div>

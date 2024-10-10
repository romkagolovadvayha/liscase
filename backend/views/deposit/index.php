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
<div class="deposit-index">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            [
                'format'    => 'raw',
                'options'   => ['width' => '32'],
                'value'     => function (Deposit $model) {
                    if (empty($model->user->userProfile)) {
                        return null;
                    }
                    return Html::img($model->user->userProfile->avatar, ['width' => '24px']);
                },
            ],
            [
                'format'    => 'raw',
                'attribute' => 'username',
                'value'          => function (Deposit $model) {
                    $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                    $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                    if (!$isAdmin && !$isModerator) {
                        return $model->user->username;
                    }
                    $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->user->id]);
                    return Html::a($model->user->username, $url);
                },
            ],
            [
                'attribute' => 'steam_id',
                'options'   => ['width' => '100'],
                'format'    => 'raw',
                'value'          => function (Deposit $model) {
                    return Html::a($model->user->steam_id, 'https://steamcommunity.com/profiles/' . $model->user->steam_id, ['target' => '_blank']);
                },
            ],
            [
                'attribute' => 'payment_type',
                'options'   => ['width' => '130'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'],  Deposit::getTypeList()),
                'value'     => function (Deposit $model) {
                    return ArrayHelper::getValue(Deposit::getTypeList(), $model->payment_type);
                },
            ],
            'amount',
            'payment_id:ntext',
            [
                'attribute' => 'status',
                'format'    => 'raw',
                'options'   => ['width' => '130'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'],  Deposit::getStatusList()),
                'value'     => function (Deposit $model) {
                    if ($model->status == Deposit::STATUS_WAIT_CONFIRM && !empty($model->payment_id)) {
                        $result = $model->debugCheck();
                        $resultName = $result;
                        if ($resultName == 'partially-paid') {
                            $resultName = "Частично оплачен";
                        }
                        return "Статус в платежной системе: {$resultName}<br/>" . ArrayHelper::getValue(Deposit::getStatusList(), $model->status);
                    }
                    return ArrayHelper::getValue(Deposit::getStatusList(), $model->status);
                },
            ],
            'created_at',
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{update}',
                'options'  => [
                    'width' => '90'
                ],
            ],
        ],
    ]); ?>
</div>

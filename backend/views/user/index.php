<?php

use common\components\helpers\Role;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\user\UserSearch;

$this->title = Yii::t('common', 'Пользователи');
?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'attribute' => 'id',
            'options'   => ['width' => '60'],
        ],
        [
            'format'    => 'raw',
            'options'   => ['width' => '32'],
            'value'     => function (UserSearch $model) {
                if (empty($model->userProfile)) {
                    return null;
                }
                return Html::img($model->userProfile->avatar, ['width' => '24px']);
            },
        ],
        [
            'attribute' => 'username',
            'label'     => 'Ник',
            'format'    => 'raw',
            'value'          => function (UserSearch $model) {
                $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                if (!$isAdmin && !$isModerator) {
                    return $model->username;
                }
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->username, $url);
            },
        ],
        [
            'attribute' => 'steam_id',
            'options'   => ['width' => '100'],
            'format'    => 'raw',
            'value'          => function (UserSearch $model) {
                return Html::a($model->steam_id, 'https://steamcommunity.com/profiles/' . $model->steam_id, ['target' => '_blank']);
            },
        ],
        [
            'attribute' => 'ref_code',
            'label'     => 'Реф.код',
            'options'   => ['width' => '100'],
            'format'    => 'raw',
            'value'          => function (UserSearch $model) {
                $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                if (!$isAdmin && !$isModerator) {
                    return $model->ref_code;
                }
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->ref_code, $url);
            },
        ],
        [
            'attribute'       => 'status',
            'options'   => ['width' => '120'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'          => \common\models\user\User::getStatusList(),
            'value'           => function (UserSearch $model) {
                $statusList = \common\models\user\User::getStatusList();
                return \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
            },
        ],
        [
            'options'   => ['width' => '200'],
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'attribute'       => 'last_visit_server_at',
            'options'   => ['width' => '200'],
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'attribute' => 'ban_by',
            'label'     => 'Модератор',
            'format'    => 'raw',
            'value'          => function (UserSearch $model) {
                $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                if (empty($model->ban_by)) {
                    return null;
                }
                $moder = \common\models\user\User::findOne($model->ban_by);
                if (!$isAdmin && !$isModerator) {
                    return $moder->username;
                }
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $moder->id]);
                return Html::a($moder->username, $url);
            },
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{switch}',
            'options'  => ['width' => '90'],
            'buttons'  => [
                'switch' => function ($url, $model) {
                    if ($model->status != UserSearch::STATUS_ACTIVE || !Yii::$app->user->can(Role::ROLE_ADMIN)) {
                        return null;
                    }

                    $url = \yii\helpers\Url::to(['/user/switch-identity', 'id' => $model->id]);
                    $btnOptions = [
                        'title' => Yii::t('common', 'Перейти в личный кабинет'),
                    ];
                    return Html::a("Войти", $url, $btnOptions);
                },
            ],
        ],
    ],
]);
?>

<?php

use common\components\helpers\Role;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\user\UserSearch;

$this->title = Yii::t('common', 'Пользователи');
?>

<?= Html::a('Генерация пользователей', ['generate'], ['class' => 'btn btn-primary', 'data' => [
    'confirm' => 'Вы уверены?',
    'method' => 'post',
]]) ?>

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
                return Html::img($model->getAvatar(), ['width' => '24px']);
            },
        ],
        [
            'attribute' => 'username',
            'format'    => 'raw',
            'options'   => ['width' => '150'],
            'value'          => function (UserSearch $model) {
                $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                $isAccountManager = Yii::$app->user->can(Role::ROLE_ACCOUNT_MANAGER);
                $isSupport = Yii::$app->user->can(Role::ROLE_SUPPORT);
                if (!$isAdmin && !$isAccountManager && !$isSupport) {
                    return $model->username;
                }
                $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                return Html::a($model->username, $url);
            },
        ],
        [
            'attribute'       => 'full_name',
            'value'          => function (UserSearch $model) {
                return $model->userProfile->full_name;
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
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{switch}',
            'options'  => ['width' => '90'],
            'buttons'  => [
                'switch' => function ($url, $model) {
                    if ($model->status != UserSearch::STATUS_ACTIVE) {
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

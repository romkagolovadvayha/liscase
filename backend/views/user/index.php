<?php

use common\components\helpers\Role;
use kartik\grid\GridView;
use yii\helpers\Html;
use common\models\user\UserSearch;

$this->title = Yii::t('common', 'Пользователи');
?>

<div class="user-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
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
                            $avatar = $model->getAvatar();
                            if (empty($avatar)) {
                                return null;
                            }
                            return Html::img($avatar, [
                                'width' => '32px',
                                'height' => '32px',
                                'style' => 'border-radius: 50%; object-fit: cover;',
                                'loading' => 'lazy',
                                'alt' => Html::encode($model->username ?? ''),
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'username',
                        'label'     => 'Ник',
                        'format'    => 'raw',
                        'value'     => function (UserSearch $model) {
                            $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                            $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                            if (!$isAdmin && !$isModerator) {
                                return Html::encode($model->username);
                            }
                            $url = \yii\helpers\Url::to(['/user/profile', 'userId' => $model->id]);
                            return Html::a(Html::encode($model->username), $url, [
                                'class' => 'ds-text--primary',
                                'style' => 'text-decoration: none;'
                            ]);
                        },
                    ],
                    [
                        'attribute' => 'steam_id',
                        'options'   => ['width' => '100'],
                        'format'    => 'raw',
                        'value'     => function (UserSearch $model) {
                            return Html::a($model->steam_id, 'https://steamcommunity.com/profiles/' . $model->steam_id, [
                                'target' => '_blank',
                                'class' => 'ds-text--primary',
                                'style' => 'text-decoration: none;'
                            ]);
                        },
                    ],
                    [
                        'attribute'       => 'status',
                        'options'   => ['width' => '120'],
                        'filterType'  => GridView::FILTER_SELECT2,
                        'filter'          => \yii\helpers\ArrayHelper::merge(['' => 'Все'], \common\models\user\User::getStatusList()),
                        'format'    => 'raw',
                        'value'           => function (UserSearch $model) {
                            $statusList = \common\models\user\User::getStatusList();
                            $status = \yii\helpers\ArrayHelper::getValue($statusList, $model->status);
                            $badgeClass = $model->status == \common\models\user\User::STATUS_ACTIVE 
                                ? 'ds-badge--success' 
                                : 'ds-badge--danger';
                            return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
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
                        'class'    => 'yii\grid\ActionColumn',
                        'template' => '{switch}',
                        'options'  => ['width' => '90'],
                        'buttons'  => [
                            'switch' => function ($url, $model) {
                                $isAdmin = Yii::$app->user->can(Role::ROLE_ADMIN);
                                $isModerator = Yii::$app->user->can(Role::ROLE_MODERATOR);
                                
                                if ($model->status != UserSearch::STATUS_ACTIVE || (!$isAdmin && !$isModerator)) {
                                    return null;
                                }

                                $url = \yii\helpers\Url::to(['/user/switch-identity', 'id' => $model->id]);
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
</div>

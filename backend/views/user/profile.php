<?php

use common\models\servers\Servers;
use common\models\user\User;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\PayoutForm;
use common\components\helpers\Role;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use backend\forms\userProfile\SkinForm;
use backend\forms\userProfile\BanForm;
use yii\helpers\Html;
use common\models\user\UserChecking;
use backend\forms\userProfile\MuteForm;
use frontend\widgets\Alert;

/** @var User $user */
/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */
/** @var SkinForm $skinForm */
/** @var BanForm $banForm */
/** @var MuteForm $muteForm */

$this->title = Yii::t('common', 'Профиль пользователя') . ': ' . Html::encode($user->username);

$userProfile = $user->userProfile;

// Рефералы
$usersTree = UserTree::find()
    ->andWhere(['parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'user_id', [$user->id]])
    ->limit(20)
    ->all();

$dataProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $usersTree,
    'totalCount' => count($usersTree),
    'pagination' => ['pageSize' => 10],
]);

$total = 0;
foreach ($usersTree as $userTree) {
    foreach ($userTree->user->deposits as $deposit) {
        if ($deposit->status === Deposit::STATUS_SUCCESS) {
            $total += $deposit->amount;
        }
    }
}

$payoutSum = \common\models\user\UserPayoutReferral::find()
    ->andWhere(['user_id' => $user->id])
    ->sum('amount') ?? 0;
$payoutTotal = $total * ($user->userProfile->referral_bonus/100) - $payoutSum;

$statusBadgeClass = $user->status === User::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';

// Команды
$servers = Servers::find()
    ->cache(30)
    ->andWhere(['status' => Servers::STATUS_ACTIVE])
    ->orderBy(['sort' => SORT_ASC])
    ->all();

$teams = [];
foreach ($servers as $server) {
    $teams = array_merge($teams, \common\models\statistics\Teams::getAllInTeams($server, $user->steam_id));
}

$teamsProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $teams,
    'totalCount' => count($teams),
    'pagination' => ['pageSize' => 30],
]);

// Проверки
$checkingExist = UserChecking::find()
    ->andWhere(['user_id' => $user->id])
    ->andWhere(['status' => UserChecking::STATUS_CHECKING])
    ->exists();

$checkings = UserChecking::find()
    ->andWhere(['user_id' => $user->id])
    ->andWhere(['status' => UserChecking::STATUS_DONE])
    ->orderBy(['id' => SORT_DESC])
    ->all();

$checkingProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $checkings,
    'totalCount' => count($checkings),
    'pagination' => ['pageSize' => 30],
]);

$bans = [];
$lastCheck = [];

$bansOtherProjectProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $bans,
    'totalCount' => count($bans),
    'pagination' => ['pageSize' => 30],
]);

$checkingOtherProjectProvider = new \yii\data\ArrayDataProvider([
    'allModels' => $lastCheck,
    'totalCount' => count($lastCheck),
    'pagination' => ['pageSize' => 30],
]);
?>

<div class="user-profile-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <?= Alert::widget() ?>

        <div class="row">
            <!-- Левая колонка: Информация о пользователе -->
            <div class="col-md-3 mb-4">
                <div class="ds-card">
                    <div class="text-center mb-3">
                        <img src="<?= $user->getAvatar() ?>" 
                             alt="<?= Html::encode($user->username) ?>"
                             loading="lazy"
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--ds-border-color, hsl(0 0% 15.3% / 1));">
                        <h3 class="mt-3 mb-1"><?= Html::encode($user->username) ?></h3>
                        <a href="https://steamcommunity.com/profiles/<?= $user->steam_id ?>" 
                           target="_blank" 
                           class="ds-text--secondary"
                           style="text-decoration: none; font-size: 0.875rem;">
                            <?= $user->steam_id ?>
                        </a>
                        <div class="mt-2">
                            <span class="ds-badge <?= $statusBadgeClass ?>">
                                <?= \yii\helpers\ArrayHelper::getValue(User::getStatusList(), $user->status) ?>
                            </span>
                        </div>
                    </div>

                    <hr class="ds-divider">

                    <!-- Действия -->
                    <div class="d-grid gap-2">
                        <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                            <button type="button" 
                                    class="ds-btn ds-btn--primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-modal-form="role_form" 
                                    data-bs-target="#modalForm">
                                <i class="fas fa-user-shield"></i> Роль пользователя
                            </button>
                            <button type="button" 
                                    class="ds-btn ds-btn--primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-modal-form="payout_form" 
                                    data-bs-target="#modalForm">
                                <i class="fas fa-money-bill-wave"></i> Вывод с реф. системы
                            </button>
                        <?php endif; ?>

                        <?php if ($user->status === User::STATUS_ACTIVE && empty($user->unbanned_at)): ?>
                            <button type="button" 
                                    class="ds-btn ds-btn--danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-modal-form="ban_form" 
                                    data-bs-target="#modalForm">
                                <i class="fas fa-ban"></i> Заблокировать игрока
                            </button>
                        <?php else: ?>
                            <?= Html::a('<i class="fas fa-unlock"></i> Снять бан', 
                                ['/user/unban', 'userId' => $user->id], 
                                [
                                    'class' => 'ds-btn ds-btn--success',
                                    'data-confirm' => 'Вы действительно уверены?'
                                ]) ?>
                        <?php endif; ?>

                        <button type="button" 
                                class="ds-btn ds-btn--primary" 
                                data-bs-toggle="modal" 
                                data-bs-modal-form="bonus_form" 
                                data-bs-target="#modalForm">
                            <i class="fas fa-coins"></i> Пополнить баланс
                        </button>

                        <?php if (!empty($user->server)): ?>
                            <a href="/statistics?StatisticsSearch%5Bsteam_id%5D=<?= $user->steam_id ?>&StatisticsSearch%5Bkey%5D=&StatisticsSearch%5Bvalue%5D=&StatisticsSearch%5Bserver_tag%5D=<?= $user->server->tag ?>&StatisticsSearch%5Bwipe%5D=<?= $user->server->currentWipe() ?>" 
                               class="ds-btn ds-btn--primary">
                                <i class="fas fa-chart-line"></i> Статистика игрока
                            </a>
                            <a href="/user-top?UserTopSearch%5Buser_id%5D=<?= $user->id ?>&UserTopSearch%5Bkey%5D=&UserTopSearch%5Bvalue%5D=&UserTopSearch%5Bserver_id%5D=<?= $user->server->id ?>&UserTopSearch%5Bwipe%5D=<?= $user->server->currentWipe() ?>" 
                               class="ds-btn ds-btn--primary">
                                <i class="fas fa-trophy"></i> Топ игрока
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Статистика -->
                <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                    <div class="ds-card mt-3">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Финансы</h5>
                        </div>
                        <div class="ds-card__body">
                            <div class="mb-3">
                                <div class="ds-text--secondary small mb-1">Лицевой баланс</div>
                                <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                    <?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> RUB
                                </div>
                            </div>
                            <div>
                                <div class="ds-text--secondary small mb-1">Доступно к выводу</div>
                                <div class="ds-text--primary" style="font-size: 1.25rem; font-weight: 600;">
                                    <?= Yii::$app->formatter->asDecimal($payoutTotal, 2) ?> RUB
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Правая колонка: Основная информация -->
            <div class="col-md-9">
                <!-- Основная информация -->
                <div class="ds-card mb-4">
                    <div class="ds-card__header">
                        <h5 class="ds-card__header-title">Основная информация</h5>
                    </div>
                    <div class="ds-card__body">
                        <?php if ($user->auto): ?>
                            <div class="mb-2">
                                <span class="ds-badge ds-badge--info">Авторегистрация</span>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="ds-text--secondary small mb-1">ID</div>
                                <div class="ds-text--primary"><?= $user->id ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="ds-text--secondary small mb-1">Steam ID</div>
                                <div class="ds-text--primary">
                                    <a href="https://steamcommunity.com/profiles/<?= $user->steam_id ?>" 
                                       target="_blank" 
                                       class="ds-text--primary"
                                       style="text-decoration: none;">
                                        <?= $user->steam_id ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="ds-text--secondary small mb-1">Статус</div>
                                <div>
                                    <span class="ds-badge <?= $statusBadgeClass ?>">
                                        <?= \yii\helpers\ArrayHelper::getValue(User::getStatusList(), $user->status) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="ds-text--secondary small mb-1">Дата регистрации</div>
                                <div class="ds-text--primary"><?= Yii::$app->formatter->asDate($user->created_at) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Команда -->
                <?php if (!empty($teams)): ?>
                    <div class="ds-card mb-4">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Команда</h5>
                        </div>
                        <div class="ds-card__body">
                            <?= \kartik\grid\GridView::widget([
                                'dataProvider' => $teamsProvider,
                                'layout' => "{items} {pager}",
                                'columns' => [
                                    [
                                        'attribute' => 'name',
                                        'label' => Yii::t('common', "Ник"),
                                        'format' => 'raw',
                                        'value' => function ($model) {
                                            $user = User::findBySteamId($model['steam_id'], false, 'profile2');
                                            if (!$user) {
                                                return Html::encode($model['name']);
                                            }
                                            return Html::a(Html::encode($model['name']), 
                                                ['/user/profile', 'userId' => $user->id],
                                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                            );
                                        },
                                    ],
                                ],
                            ]); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Проверки -->
                <?php if (!empty($checkings)): ?>
                    <div class="ds-card mb-4">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Проверки</h5>
                        </div>
                        <div class="ds-card__body">
                            <?= \kartik\grid\GridView::widget([
                                'dataProvider' => $checkingProvider,
                                'layout' => "{items} {pager}",
                                'columns' => [
                                    [
                                        'label' => Yii::t('common', "Кто вызывал"),
                                        'format' => 'raw',
                                        'value' => function (UserChecking $model) {
                                            $user = User::findOne($model->checking_by);
                                            if (!$user) {
                                                return '-';
                                            }
                                            return Html::a(Html::encode($user->username), 
                                                ['/user/profile', 'userId' => $user->id],
                                                ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                            );
                                        },
                                    ],
                                    [
                                        'attribute' => 'created_at',
                                        'label' => Yii::t('common', "Начало проверки"),
                                        'value' => function (UserChecking $model) {
                                            return Yii::$app->formatter->asDatetime($model->created_at);
                                        },
                                    ],
                                    [
                                        'attribute' => 'done_at',
                                        'label' => Yii::t('common', "Завершение проверки"),
                                        'value' => function (UserChecking $model) {
                                            return $model->done_at ? Yii::$app->formatter->asDatetime($model->done_at) : '-';
                                        },
                                    ],
                                ],
                            ]); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Редактирование пользователя -->
                <?= $this->render('_form', ['model' => $user]) ?>

                <!-- Рефералы -->
                <?php if (!empty($usersTree)): ?>
                    <div class="ds-card mb-4">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Рефералы</h5>
                        </div>
                        <div class="ds-card__body">
                            <?= \kartik\grid\GridView::widget([
                                'dataProvider' => $dataProvider,
                                'layout' => "{items} {pager}",
                                'columns' => [
                                    [
                                        'label' => Yii::t('common', "Ник"),
                                        'format' => 'raw',
                                        'value' => function (UserTree $model) {
                                            return Html::encode($model->user->username);
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Более часа на сервере"),
                                        'format' => 'raw',
                                        'value' => function (UserTree $model) {
                                            $badgeClass = $model->user->userProfile->parent_bonus 
                                                ? 'ds-badge--success' 
                                                : 'ds-badge--danger';
                                            $text = $model->user->userProfile->parent_bonus 
                                                ? Yii::t('common', "Да") 
                                                : Yii::t('common', "Нет");
                                            return Html::tag('span', $text, ['class' => 'ds-badge ' . $badgeClass]);
                                        },
                                    ],
                                    [
                                        'attribute' => 'created_at',
                                        'label' => Yii::t('common', "Дата регистрации"),
                                        'value' => function (UserTree $model) {
                                            return Yii::$app->formatter->asDate($model->user->created_at);
                                        },
                                    ],
                                    [
                                        'label' => '',
                                        'format' => 'raw',
                                        'value' => function (UserTree $model) use ($user) {
                                            return Html::a('Отвязать', 
                                                ['/user/revoke', 'parentId' => $model->parent_user_id, 'userId' => $model->user_id],
                                                [
                                                    'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                                    'data-confirm' => 'Вы действительно хотите отвязать этого пользователя?'
                                                ]
                                            );
                                        },
                                    ],
                                ],
                            ]); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Проверки на других проектах -->
                <?php if (!empty($lastCheck)): ?>
                    <div class="ds-card mb-4">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Проверки на других проектах</h5>
                        </div>
                        <div class="ds-card__body">
                            <?= \kartik\grid\GridView::widget([
                                'dataProvider' => $checkingOtherProjectProvider,
                                'layout' => "{items} {pager}",
                                'columns' => [
                                    [
                                        'label' => Yii::t('common', "Сервер"),
                                        'value' => function ($model) {
                                            return Html::encode($model['serverName'] ?? '-');
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Модератор"),
                                        'format' => 'raw',
                                        'value' => function ($model) {
                                            if (empty($model['moder'])) {
                                                return '-';
                                            }
                                            return Html::a(
                                                Html::encode($model['moder']->username),
                                                "https://steamcommunity.com/profiles/{$model['moder']->steam_id}",
                                                ['target' => '_blank', 'class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                                            );
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Дата"),
                                        'value' => function ($model) {
                                            return Html::encode($model['date'] ?? '-');
                                        },
                                    ],
                                ],
                            ]); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Баны на других проектах -->
                <?php if (!empty($bans)): ?>
                    <div class="ds-card mb-4">
                        <div class="ds-card__header">
                            <h5 class="ds-card__header-title">Баны на других проектах</h5>
                        </div>
                        <div class="ds-card__body">
                            <?= \kartik\grid\GridView::widget([
                                'dataProvider' => $bansOtherProjectProvider,
                                'layout' => "{items} {pager}",
                                'columns' => [
                                    [
                                        'label' => Yii::t('common', "Сервер"),
                                        'value' => function ($model) {
                                            return Html::encode($model['serverName'] ?? '-');
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Причина"),
                                        'value' => function ($model) {
                                            return Html::encode($model['reason'] ?? '-');
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Дата"),
                                        'value' => function ($model) {
                                            return Html::encode($model['date'] ?? '-');
                                        },
                                    ],
                                    [
                                        'label' => Yii::t('common', "Разбан"),
                                        'value' => function ($model) {
                                            return Html::encode($model['unbanned_date'] ?? '-');
                                        },
                                    ],
                                ],
                            ]); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalForm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="role_form" style="display: none;">
                <?= $this->render('_form_role', compact('roleForm')) ?>
            </div>
            <div id="bonus_form" style="display: none;">
                <?= $this->render('_form_personal_bonus', compact('bonusForm')) ?>
            </div>
            <div id="payout_form" style="display: none;">
                <?= $this->render('_form_payout_form', compact('payoutForm')) ?>
            </div>
            <div id="ban_form" style="display: none;">
                <?= $this->render('_form_ban_form', compact('banForm')) ?>
            </div>
            <div id="mute_form" style="display: none;">
                <?= $this->render('_form_mute_form', compact('muteForm')) ?>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
    $('[data-bs-modal-form]').on('click', function () {
        var form = $($(this).data().bsTarget);
        var element = $("#" + $(this).data().bsModalForm);
        form.find('.modal-content > *').hide();
        element.show();
    });
JS
);
?>

<?php

use common\components\oauth\Steam;
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
use yii\bootstrap5\Html;
use common\models\user\UserChecking;
use backend\forms\userProfile\MuteForm;
use yii\web\View;
use frontend\widgets\Alert;

/** @var User $user */
$this->title = Yii::t('common', 'Профиль');

/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */
/** @var SkinForm $skinForm */
/** @var BanForm $banForm */
/** @var MuteForm $muteForm */
/** @var UserProfile $userProfile */

$userProfile = $user->userProfile;

$usersTree = UserTree::find()
                     ->andWhere(['parent_user_id' => $user->id])
                     ->andWhere(['NOT IN', 'user_id', [$user->id]])
                     ->limit(20)
                     ->all();

$dataProvider = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $usersTree,
                                                    'totalCount' => count($usersTree),
                                                    'pagination' => [
                                                        'pageSize' => 10,
                                                    ],
                                                ]);
$total = 0;
/** @var User[] $users */
/** @var UserTree[] $usersTree */
foreach ($usersTree as $i => $userTree) {
    /** @var Deposit $deposit */
    foreach ($userTree->user->deposits as $deposit) {
        if ($deposit->status !== Deposit::STATUS_SUCCESS) {
            continue;
        }
        $total += $deposit->amount;
    }
}
$payoutSum = \common\models\user\UserPayoutReferral::find()
                                                   ->andWhere(['user_id' => $user->id])
                                                   ->sum('amount') ?? 0;
$payoutTotal = $total * ($user->userProfile->referral_bonus/100) - $payoutSum;

$statusClass = "bg-success";
if ($user->status === 5) {
    $statusClass = "bg-danger";
}


/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->andWhere(['status' => Servers::STATUS_ACTIVE])
                  ->orderBy(['sort' => SORT_ASC])
                  ->all();

$teams = [];
foreach ($servers as $server) {
    $teams = array_merge($teams, \common\models\statistics\Teams::getAllInTeams($server, $user->steam_id));
}
$teamsProvider2 = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $teams,
                                                    'totalCount' => count($teams),
                                                    'pagination' => [
                                                        'pageSize' => 30,
                                                    ],
]);

$checkingExist = UserChecking::find()
    ->andWhere(['user_id' => $user->id])
    ->andWhere(['status' => UserChecking::STATUS_CHECKING])
    ->exists();

/** @var UserChecking[] $checkings */
$checkings = UserChecking::find()
    ->andWhere(['user_id' => $user->id])
    ->andWhere(['status' => UserChecking::STATUS_DONE])
    ->orderBy(['id' => SORT_DESC])
    ->all();

$checkingProvider = new \yii\data\ArrayDataProvider([
                                                        'allModels' => $checkings,
                                                        'totalCount' => count($checkings),
                                                        'pagination' => [
                                                            'pageSize' => 30,
                                                        ],
                                                    ]);
$bans = [];
$bansExist = false;
$lastCheck = [];
$lastCheckExist = false;

$bansOtherProjectProvider = new \yii\data\ArrayDataProvider([
                                                                    'allModels' => $bans,
                                                                    'totalCount' => count($bans),
                                                                    'pagination' => [
                                                                        'pageSize' => 30,
                                                                    ],
                                                                ]);
$checkingOtherProjectProvider = new \yii\data\ArrayDataProvider([
                                                        'allModels' => $lastCheck,
                                                        'totalCount' => count($lastCheck),
                                                        'pagination' => [
                                                            'pageSize' => 30,
                                                        ],
                                                    ]);
?>

<style>
    .dagner {
        color: #ffffff;
        background-color: #a94442;
    }
    .success {
        color: #ffffff;
        background-color: #3c763d;
    }
</style>
<?=\frontend\widgets\Alert::widget()?>
<div class="row">
    <div class="text-center col-md-2">
        <img style="border-radius: 3px;" src="<?=$user->getAvatar()?>"/>
        <h3 style="margin-top: 15px; text-align: center"><?= $user->username; ?></h3>
        <a href="https://steamcommunity.com/profiles/<?=$user->steam_id?>" class="stats_player_card_body_name_steam" target="_blank" title="<?=Yii::t('common', 'Перейти в профиль Steam')?>"><?=$user->steam_id?></a>
        <div class="list-group" style="margin-top: 15px; text-align: left">
            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                <button type="button" class="list-group-item list-group-item-action list-group-item-info" data-bs-toggle="modal" data-bs-modal-form="role_form" data-bs-target="#modalForm">
                    Роль пользователя
                </button>
                <button type="button" class="list-group-item list-group-item-action list-group-item-warning" data-bs-toggle="modal" data-bs-modal-form="payout_form" data-bs-target="#modalForm">
                    Вывод с реф. системы
                </button>
            <?php endif; ?>
                <?php if ($user->status === User::STATUS_ACTIVE && empty($user->unbanned_at)): ?>
                <button type="button" class="list-group-item list-group-item-action list-group-item-danger" data-bs-toggle="modal" data-bs-modal-form="ban_form" data-bs-target="#modalForm">
                    Заблокировать игрока
                </button>
                <?php else: ?>
                <?= Html::a('Снять бан', '/user/unban?userId=' . $user->id, ['data-confirm' => 'Вы действительно уверены?', 'class' => 'list-group-item list-group-item-action list-group-item-success']) ?>
                <?php endif; ?>
                <?php if (!$checkingExist): ?>
                    <?= Html::a('Вызвать на проверку', '/user/checking-start?userId=' . $user->id, ['data-confirm' => 'Вы действительно уверены?', 'class' => 'list-group-item list-group-item-action list-group-item-primary']) ?>
                <?php else: ?>
                    <?= Html::a('Завершить проверку', '/user/checking-stop?userId=' . $user->id, ['data-confirm' => 'Вы действительно уверены?', 'class' => 'list-group-item list-group-item-action list-group-item-success']) ?>
                <?php endif; ?>
            <button type="button" class="list-group-item list-group-item-action list-group-item-warning" data-bs-toggle="modal" data-bs-modal-form="bonus_form" data-bs-target="#modalForm">
                Пополнить баланс
            </button>
            <a type="button" class="list-group-item list-group-item-action list-group-item-primary" href="/statistics?StatisticsSearch%5Bsteam_id%5D=<?=$user->steam_id?>&StatisticsSearch%5Bkey%5D=&StatisticsSearch%5Bvalue%5D=&StatisticsSearch%5Bserver_tag%5D=<?=$user->server->tag?>&StatisticsSearch%5Bwipe%5D=<?=$user->server->currentWipe()?>">
                Статистика игрока
            </a>
            <a type="button" class="list-group-item list-group-item-action list-group-item-primary" href="/user-top?UserTopSearch%5Buser_id%5D=<?=$user->id?>&UserTopSearch%5Bkey%5D=&UserTopSearch%5Bvalue%5D=&UserTopSearch%5Bserver_id%5D=<?=$user->server->id?>&UserTopSearch%5Bwipe%5D=<?=$user->server->currentWipe()?>">
                Топ игрока
            </a>
        </div>
    </div>
    <div class="col-md-10">
        <ul class="list-group">
            <?php if ($user->auto):?>
                <li class="list-group-item">Авторегистрация</li>
            <?php endif; ?>
            <li class="list-group-item"><?= $user->username; ?> <span class="badge rounded-pill <?=$statusClass?>"><?= \yii\helpers\ArrayHelper::getValue(User::getStatusList(), $user->status); ?></span></li>
            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)):?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= Yii::t('common', 'Лицевой баланс'); ?>
                <span class="badge bg-primary rounded-pill"><?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> RUB</span>
            </li>
            <?php endif; ?>
            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)):?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= Yii::t('common', 'Доступно к выводу'); ?>
                <span class="badge bg-primary rounded-pill"><?= Yii::$app->formatter->asDecimal($payoutTotal, 2) ?> RUB</span>
            </li>
            <?php endif; ?>
        </ul>
        <div class="mt-4">
            <h3>Команда</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $teamsProvider2,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'name',
                                                          'label'     => Yii::t('common', "Ник"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              $user = User::findBySteamId($model['steam_id'], false, 'profile2');
                                                              return "<a href=\"/user/profile?userId={$user->id}\">{$model['name']}</a>";
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
        <div class="mt-4">
            <h3>Проверки</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $checkingProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'name',
                                                          'label'     => Yii::t('common', "Кто вызывал"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserChecking $model) {
                                                              $user = User::findOne($model->checking_by);
                                                              return "<a href=\"/user/profile?userId={$user->id}\">{$user->username}</a>";
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'created_at',
                                                          'label'     => Yii::t('common', "Начало проверки"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserChecking $model) {
                                                              return $model->created_at;
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'done_at',
                                                          'label'     => Yii::t('common', "Завершение проверки"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserChecking $model) {
                                                              return $model->done_at;
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
        <?= $this->render('_form', [
            'model' => $user,
        ]) ?>
        <div class="mt-4">
            <h3>Рефералы</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $dataProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'username',
                                                          'label'     => Yii::t('common', "Ник"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              return $model->user->username;
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'online',
                                                          'options'   => ['width' => '200'],
                                                          'label'     => Yii::t('common', "Более часа на сервере"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              if ($model->user->userProfile->parent_bonus) {
                                                                  return Yii::t('common', "Да");
                                                              }
                                                              return Yii::t('common', "Нет");
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'created_at',
                                                          'options'   => ['width' => '200'],
                                                          'label'     => Yii::t('common', "Дата регистрации"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) {
                                                              return $model->user->created_at;
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'replay',
                                                          'options'   => ['width' => '150'],
                                                          'label'     => Yii::t('common', ""),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) use ($user) {
                                                              return '<a href="/user/revoke?parentId=' . $model->parent_user_id . '&userId=' . $model->user_id . '" class="btn btn-danger">Отвязать</a>';
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
        <div class="mt-4">
            <h3>Проверки на других проектах</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $checkingOtherProjectProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'serverName',
                                                          'label'     => Yii::t('common', "Сервер"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['serverName'];
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'moder',
                                                          'label'     => Yii::t('common', "Модератор"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                                if (empty($model['moder'])) {
                                                                    return "Не указан";
                                                                }
                                                              return "<a href=\"https://steamcommunity.com/profiles/{$model['moder']->steam_id}\">{$model['moder']->username}</a>";
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'date',
                                                          'label'     => Yii::t('common', "Дата"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['date'];
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
        <div class="mt-4">
            <h3>Баны на других проектах</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $bansOtherProjectProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'serverName',
                                                          'label'     => Yii::t('common', "Сервер"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['serverName'];
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'reason',
                                                          'label'     => Yii::t('common', "Причина"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['reason'];
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'date',
                                                          'label'     => Yii::t('common', "Дата"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['date'];
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'date',
                                                          'label'     => Yii::t('common', "Разбан"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return $model['unbanned_date'];
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalForm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="role_form">
                <?= $this->render('_form_role', compact('roleForm')); ?>
            </div>
            <div id="bonus_form">
                <?= $this->render('_form_personal_bonus', compact('bonusForm')); ?>
            </div>
            <div id="payout_form">
                <?= $this->render('_form_payout_form', compact('payoutForm')); ?>
            </div>
            <div id="ban_form">
                <?= $this->render('_form_ban_form', compact('banForm')); ?>
            </div>
            <div id="mute_form">
                <?= $this->render('_form_mute_form', compact('muteForm')); ?>
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

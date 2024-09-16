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
use yii\web\View;
use frontend\widgets\Alert;

/** @var User $user */
$this->title = Yii::t('common', 'Профиль');

/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */
/** @var SkinForm $skinForm */
/** @var BanForm $banForm */
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


$minPrice = 20;
$maxPrice = 50;
$items = [];
$data = Yii::$app->rustTm->prices()['items'];
shuffle($data);
foreach ($data as $item) {
    if ($item['price'] > $item['avg_price'] + 5) {
        continue;
    }
    if ($item['price'] > $maxPrice || $item['price'] < $minPrice) {
        continue;
    }
    $items[] = [
        "name" => $item['market_hash_name'],
        "price" => $item['price'],
        "image" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/100.png",
        "image300" => "https://cdn.rust.tm/item/" . urlencode($item['market_hash_name']) . "/300.png"
    ];
    if (count($items) > 40) {
        break;
    }
}
$items = array_slice($items, 0, 60);
$dataProviderSkins = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $items,
                                                    'totalCount' => count($items),
                                                    'pagination' => [
                                                        'pageSize' => 60,
                                                    ],
                                                ]);

$statusClass = "bg-success";
if ($user->status === 5) {
    $statusClass = "bg-danger";
}


/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
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
try {
    $rustCheck = Yii::$app->rustCheck->getInfo($user->steam_id);
    if (!empty($rustCheck['bans'])) {
        foreach ($rustCheck['bans'] as $ban) {
            $bansExist = true;
            $date = new DateTime();
            $date->setTimestamp($ban['banDate']);
            $unbannedDate = $ban['unbanDate'];
            if (!empty($unbannedDate)) {
                $date2 = new DateTime();
                $date2->setTimestamp($unbannedDate);
                $unbannedDate = $date->format('d.m.Y H:i:s');
            } else {
                $unbannedDate = "Никогда";
            }
            if (strpos($ban['serverName'], 'Без названия') !== false) {
                $ban['serverName'] = '<i style="color: #808080">Сервер удален</i>';
            }
            $bans[] = [
                'serverName' => $ban['serverName'],
                'reason' => $ban['reason'],
                'unbanned_date' => $unbannedDate,
                'date' => $date->format('d.m.Y H:i:s'),
            ];
        }
    }
    if (!empty($rustCheck['last_check'])) {
        foreach ($rustCheck['last_check'] as $_lastCheck) {
            $lastCheckExist = true;
            $moder = null;
            if (!empty($_lastCheck['moderSteamID'])) {
                $moder = User::findBySteamId($_lastCheck['moderSteamID']);
            }
            $date = new DateTime();
            $date->setTimestamp($_lastCheck['time']);
            $lastCheck[] = [
                'serverName' => $_lastCheck['serverName'],
                'date' => $date->format('d.m.Y H:i:s'),
                'moder' => $moder,
            ];
        }
    }
} catch (\Exception $e) {
    Yii::$app->telegramReports->sendMessage("Profile:" . $e->getLine() . ":" . $e->getMessage());
}


try {
    $banList = Steam::getBansGGRust($user->steam_id);
    foreach ($banList as $banItem) {
        $bansExist = true;
        $bans[] = [
            'serverName' => $banItem['server'],
            'reason' => $banItem['reason'],
            'unbanned_date' => $banItem['expireDate'],
            'date' => $banItem['date'],
        ];
    }
} catch (\Exception $e) {
    Yii::$app->telegramReports->sendMessage("Profile:" . $e->getLine() . ":" . $e->getMessage());
}
try {
    $banList = Steam::getBansRustRoom($user->steam_id);
    foreach ($banList as $banItem) {
        $bansExist = true;
        $date = new DateTime();
        $date->setTimestamp($banItem['date']);
        $bans[] = [
            'serverName' => $banItem['server'],
            'reason' => $banItem['reason'],
            'unbanned_date' => $banItem['expireDate'],
            'date' => $date->format('d.m.Y H:i:s'),
        ];
    }
} catch (\Exception $e) {
    Yii::$app->telegramReports->sendMessage("Profile:" . $e->getLine() . ":" . $e->getMessage());
}
try {
    $banList = Steam::getBansRustUssr($user->steam_id);
    foreach ($banList as $banItem) {
        $bansExist = true;
        $bans[] = [
            'serverName' => $banItem['server'],
            'reason' => $banItem['reason'],
            'unbanned_date' => $banItem['expireDate'],
            'date' =>  $banItem['date'],
        ];
    }
} catch (\Exception $e) {
    Yii::$app->telegramReports->sendMessage("Profile:" . $e->getLine() . ":" . $e->getMessage());
}
try {
    $banList = Steam::getBansMagicRust($user->steam_id);
    foreach ($banList as $banItem) {
        $bansExist = true;
        $date = new DateTime();
        $date->setTimestamp($banItem['time']);
        $bans[] = [
            'serverName' => $banItem['server'],
            'reason' => $banItem['reason'],
            'unbanned_date' => $banItem['expireDate'],
            'date' => $date->format('d.m.Y H:i:s'),
        ];
    }
} catch (\Exception $e) {
    Yii::$app->telegramReports->sendMessage("Profile:" . $e->getLine() . ":" . $e->getMessage());
}



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
        <img style="border-radius: 3px;" src="<?=$userProfile->avatar?>"/>
        <h3 style="margin-top: 15px; text-align: center"><?= $user->username; ?></h3>
        <a href="https://steamcommunity.com/profiles/<?=$user->steam_id?>" class="stats_player_card_body_name_steam" target="_blank" title="<?=Yii::t('common', 'Перейти в профиль Steam')?>"><?=$user->steam_id?></a>
        <div class="list-group" style="margin-top: 15px; text-align: left">
            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                <button type="button" class="list-group-item list-group-item-action list-group-item-info" data-bs-toggle="modal" data-bs-modal-form="role_form" data-bs-target="#modalForm">
                    Роль пользователя
                </button>
                <button type="button" class="list-group-item list-group-item-action list-group-item-warning" data-bs-toggle="modal" data-bs-modal-form="bonus_form" data-bs-target="#modalForm">
                    Пополнить баланс
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
                                                              $user = User::findBySteamId($model['steam_id']);
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
                                                              User::parentBonus($model->user);
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
                                                          'label'     => Yii::t('common', "Отправить скин"),
                                                          'format'    => 'raw',
                                                          'value'          => function (UserTree $model) use ($user) {
                                                              if ($model->user->parent_skin_send) {
                                                                  return '';
                                                              }
                                                              return '<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-modal-form="skin_form_' . $model->user->id . '" data-bs-target="#modalForm">Отправить</button>';
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
            <?php foreach ($usersTree as $userTree): ?>
                <div id="skin_form_<?=$userTree->user->id?>">
                    <?= $this->render('_form_skin_form', [
                        'childId' => $userTree->user->id,
                        'dataProviderSkins' => $dataProviderSkins,
                        'user' => $user,
                    ]); ?>
                </div>
            <?php endforeach; ?>
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

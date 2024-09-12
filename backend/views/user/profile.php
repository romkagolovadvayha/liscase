<?php

use common\models\user\User;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\PayoutForm;
use common\components\helpers\Role;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use backend\forms\userProfile\SkinForm;
use yii\web\View;
use frontend\widgets\Alert;

/** @var User $user */
$this->title = Yii::t('common', 'Профиль');

/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var PayoutForm $payoutForm */
/** @var SkinForm $skinForm */
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
        </div>
    </div>
    <div class="col-md-10">
        <ul class="list-group">
            <?php if ($user->auto):?>
                <li class="list-group-item">Авторегистрация</li>
            <?php endif; ?>
            <li class="list-group-item">Ник: <?= $user->username; ?></li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= Yii::t('common', 'Лицевой баланс'); ?>
                <span class="badge bg-primary rounded-pill"><?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> RUB</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= Yii::t('common', 'Доступно к выводу'); ?>
                <span class="badge bg-primary rounded-pill"><?= Yii::$app->formatter->asDecimal($payoutTotal, 2) ?> RUB</span>
            </li>
        </ul>
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
    </div>
</div>

<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalForm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="role_form">
                    <?= $this->render('_form_role', compact('roleForm')); ?>
                </div>
                <div id="bonus_form">
                    <?= $this->render('_form_personal_bonus', compact('bonusForm')); ?>
                </div>
                <div id="payout_form">
                    <?= $this->render('_form_payout_form', compact('payoutForm')); ?>
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
</div>

<?php
$this->registerJs(<<<JS
    $('[data-bs-modal-form]').on('click', function () {
        var form = $($(this).data().bsTarget);
        var element = $("#" + $(this).data().bsModalForm);
        form.find('.modal-body > *').hide();
        element.show();
    });
JS
);
?>

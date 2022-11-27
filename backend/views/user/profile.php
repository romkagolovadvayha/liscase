<?php

use common\models\user\User;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\BonusForm;
use common\components\helpers\Role;
use common\models\user\UserProfile;

/** @var User $user */
$this->title = Yii::t('common', 'Профиль');

/** @var RoleForm $roleForm */
/** @var BonusForm $bonusForm */
/** @var UserProfile $userProfile */

$userProfile = $user->userProfile;

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
<div class="row">
    <div class="text-center col-md-2">
        <img src="<?=Yii::$app->user->identity->userProfile->avatar?>"/>
        <div class="list-group" style="margin-top: 20px; text-align: left">
            <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                <button type="button" class="list-group-item list-group-item-action list-group-item-info" data-bs-toggle="modal" data-bs-modal-form="role_form" data-bs-target="#modalForm">
                    Роль пользователя
                </button>
                <button type="button" class="list-group-item list-group-item-action list-group-item-warning" data-bs-toggle="modal" data-bs-modal-form="bonus_form" data-bs-target="#modalForm">
                    Пополнить баланс
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4 col-lg-5">
        <ul class="list-group">
            <?php if (!empty($userProfile->full_name)):?>
                <li class="list-group-item">Имя: <?= $userProfile->full_name ?></li>
            <?php endif; ?>
            <?php if (!empty($userProfile->birthday)):?>
                <li class="list-group-item">Дата рождения: <?= $userProfile->birthday ?></li>
            <?php endif; ?>
            <?php if (!empty($userProfile->phone)):?>
                <li class="list-group-item">Телефон: <?= $userProfile->phone ?></li>
            <?php endif; ?>
            <?php if (!empty($userProfile->gender)):?>
                <li class="list-group-item">Пол: <?= \yii\helpers\ArrayHelper::getValue(UserProfile::getGenderList(), $userProfile->gender) ?></li>
            <?php endif; ?>
            <li class="list-group-item">E-mail: <?= $user->email; ?></li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= Yii::t('common', 'Лицевой баланс'); ?>
                <span class="badge bg-primary rounded-pill"><?= Yii::$app->formatter->asDecimal($user->getPersonalBalance()->getBalanceCeil(), 2) ?> RUB</span>
            </li>
        </ul>
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

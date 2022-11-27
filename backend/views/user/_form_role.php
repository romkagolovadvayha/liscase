<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\RoleForm;

/* @var $this yii\web\View */
/* @var $roleForm RoleForm */
?>

<div class="panel panel-info">
    <div class="panel-heading"><h3 class="panel-title">Роль пользователя</h3></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin() ?>
        <?= $form->field($roleForm, 'roleCodes')
                 ->dropDownList(\common\models\user\UserSearch::authRolesNames(),
                     [
                         'multiple'=>'multiple',
                         'size'=>'10',
                         'class'=>'chosen-select input-md required',
                     ]); ?>
        <?= Html::submitButton('Сохранить', ['data-confirm' => 'Вы действительно хотите сменить роль?', 'class' => 'btn btn-success']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
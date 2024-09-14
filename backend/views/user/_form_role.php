<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\RoleForm;

/* @var $this yii\web\View */
/* @var $roleForm RoleForm */
?>

<div class="modal-header">
    <h5 class="modal-title">Роль пользователя</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
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
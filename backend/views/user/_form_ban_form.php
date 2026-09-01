<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\BanForm;
use common\models\user\User;

/* @var $this yii\web\View */
/* @var $banForm BanForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Бан игрока</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть окно"></button>
</div>
<div class="modal-body">
    <?php $form = ActiveForm::begin() ?>
    <div class="option_bans">
    <?= $form->field($banForm, 'reason',
                     [
                         'errorOptions'   => [
                             'encode' => false,
                             'class'  => 'help-block',
                         ],
                         'template' => '{input}<div>{error}{hint}</div>',
                     ])
             ->radioList(User::getReasonList(), [
                 'item' => function ($index, $label, $name, $checked, $value) {
                     $id = 'option_' . $index;

                     $return = Html::radio($name, $checked, [
                         'id'    => $id,
                         'value' => $value,
                         'class' => 'form-check-input',
                     ]);

                     $return .= Html::label($label, $id, [
                         'class' => 'form-check-label'
                     ]);

                     return Html::tag('div', $return,
                                      ['class' => 'form-check item-' . $index]);
                 },
             ])->label(false); ?>
    </div>
    <?= Html::submitButton('Заблокировать', ['data-confirm' => 'Вы действительно уверены?', 'class' => 'btn btn-success']) ?>
    <?php ActiveForm::end() ?>
</div>

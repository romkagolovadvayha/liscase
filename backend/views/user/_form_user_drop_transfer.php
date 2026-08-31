<?php

use backend\forms\userProfile\UserDropTransferForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/* @var $this yii\web\View */
/* @var $userDropTransferForm UserDropTransferForm */

$rowsCount = $userDropTransferForm->getTransferableRowsCount();
?>

<div class="modal-header">
    <h5 class="modal-title" id="user-drop-transfer-title">Перенести предметы</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
</div>
<div class="modal-body">
    <p class="user-profile-modal__hint" id="user-drop-transfer-description">
        Все предметы со статусом «Доступен» будут перенесены на другой аккаунт. Уже выданные, проданные, временно заблокированные и находящиеся в отправке предметы останутся на текущем аккаунте.
    </p>

    <div class="ds-alert <?= $rowsCount > 0 ? 'ds-alert--info' : 'ds-alert--warning' ?> mb-3" role="status">
        <div class="ds-alert__icon" aria-hidden="true">
            <i class="fas <?= $rowsCount > 0 ? 'fa-box-open' : 'fa-exclamation-triangle' ?>"></i>
        </div>
        <div class="ds-alert__content">
            <div class="ds-alert__message">
                <?= $rowsCount > 0
                    ? 'Количество записей для переноса: ' . Html::encode((string) $rowsCount)
                    : 'У пользователя нет доступных предметов для переноса.' ?>
            </div>
        </div>
    </div>

    <?php $form = ActiveForm::begin([
        'id' => 'user-drop-transfer-form-element',
        'options' => [
            'novalidate' => true,
            'aria-labelledby' => 'user-drop-transfer-title',
            'aria-describedby' => 'user-drop-transfer-description',
        ],
    ]) ?>

    <?= $form->errorSummary($userDropTransferForm, ['class' => 'alert alert-danger mb-3']) ?>
    <?= $form->field($userDropTransferForm, 'recipientSteamId')->textInput([
        'id' => 'user-drop-transfer-steam-id',
        'placeholder' => '76561198…',
        'inputmode' => 'numeric',
        'autocomplete' => 'off',
        'maxlength' => 20,
        'aria-required' => 'true',
        'aria-describedby' => 'user-drop-transfer-lookup-status',
        'disabled' => $rowsCount < 1,
    ]) ?>

    <div id="user-drop-transfer-lookup-status" class="form-text" role="status" aria-live="polite" aria-atomic="true">
        <?= $rowsCount > 0 ? 'Введите Steam ID, чтобы проверить получателя.' : '' ?>
    </div>

    <div id="user-drop-transfer-recipient" class="ds-card mt-3" hidden>
        <div class="ds-card__body p-3 flex items-center gap-3">
            <img id="user-drop-transfer-avatar" class="w-12 h-12 rounded-full object-cover flex-shrink-0" src="" alt="" hidden>
            <span id="user-drop-transfer-avatar-fallback" class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" aria-hidden="true">
                <i class="fas fa-user"></i>
            </span>
            <span class="min-w-0">
                <strong id="user-drop-transfer-name" class="block truncate"></strong>
                <span id="user-drop-transfer-steam" class="block form-text truncate"></span>
            </span>
        </div>
    </div>

    <?= Html::submitButton(
        '<i class="fas fa-exchange-alt" aria-hidden="true"></i> Подтвердить перенос',
        [
            'id' => 'user-drop-transfer-submit',
            'class' => 'ds-btn ds-btn--primary w-100 mt-3',
            'disabled' => true,
            'aria-disabled' => 'true',
            'data-confirm' => 'Перенести все доступные предметы выбранному пользователю?',
        ]
    ) ?>

    <?php ActiveForm::end() ?>
</div>

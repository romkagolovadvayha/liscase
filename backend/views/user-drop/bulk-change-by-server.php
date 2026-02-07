<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\servers\Servers;

$this->title = Yii::t('common', 'Массовое изменение статусов по серверу');
?>

<div class="user-drop-bulk-change-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <p class="text-muted">
                Выберите сервер и диапазон дат для изменения статусов предметов со статуса "Отправлен" (2) на "Доступен" (1).
                Будут изменены только предметы пользователей, которые играли на выбранном сервере в период текущего wipe.
            </p>

            <form method="post" class="form-horizontal">
                <?= Yii::$app->request->csrfParam ? Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) : '' ?>

                <div class="form-group">
                    <label class="control-label">Сервер</label>
                    <?= Html::dropDownList(
                        'server_id',
                        $serverId ?? '',
                        ArrayHelper::merge(['' => 'Выберите сервер'], $serversList),
                        ['class' => 'form-control', 'required' => true]
                    ) ?>
                </div>

                <div class="form-group">
                    <label class="control-label">Дата начала (sended_at)</label>
                    <?= Html::input('datetime-local', 'date_from', $dateFrom ?? '', [
                        'class' => 'form-control',
                        'required' => true,
                    ]) ?>
                </div>

                <div class="form-group">
                    <label class="control-label">Дата окончания (sended_at)</label>
                    <?= Html::input('datetime-local', 'date_to', $dateTo ?? '', [
                        'class' => 'form-control',
                        'required' => true,
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= Html::submitButton('Показать список', ['class' => 'ds-btn ds-btn--primary']) ?>
                    <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
                </div>
            </form>
        </div>
    </div>
</div>


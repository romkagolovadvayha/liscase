<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\servers\Servers;

$this->title = Yii::t('common', 'Начисление бонуса игрокам сервера');
?>

<div class="user-drop-bonus-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <p class="text-muted">
                Выберите сервер для начисления бонуса всем игрокам, которые играли на сервере в текущем wipe.
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
                    <label class="control-label">Сумма бонуса (руб.)</label>
                    <?= Html::input('number', 'amount', $amount ?? '', [
                        'class' => 'form-control',
                        'required' => true,
                        'min' => 0.01,
                        'step' => 0.01,
                    ]) ?>
                </div>

                <div class="form-group">
                    <label class="control-label">Комментарий</label>
                    <?= Html::textInput('comment', $comment ?? '', [
                        'class' => 'form-control',
                        'maxlength' => 255,
                        'required' => true,
                    ]) ?>
                    <small class="form-text text-muted">Комментарий будет отображаться в истории операций пользователя. К нему автоматически добавится название сервера в скобках.</small>
                </div>

                <div class="form-group">
                    <?= Html::submitButton('Показать список', ['class' => 'ds-btn ds-btn--primary']) ?>
                    <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
                </div>
            </form>
        </div>
    </div>
</div>


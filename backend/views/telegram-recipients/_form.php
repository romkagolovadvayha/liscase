<?php

use backend\models\TelegramRecipients;
use kartik\form\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

/** @var TelegramRecipients $model */
?>
<div class="mailing-page mailing-audience-form-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>

    <header class="mailing-page-head mailing-page-head--compact">
        <div>
            <span class="mailing-page-head__eyebrow">Аудитория</span>
            <h1><?= $model->isNewRecord ? 'Новая аудитория' : 'Редактирование аудитории' ?></h1>
            <p>Дайте группе понятное название и найдите пользователей по имени, ID, Steam ID или реферальному коду.</p>
        </div>
    </header>

    <?php $form = ActiveForm::begin(['id' => 'telegram-recipients-form', 'options' => ['class' => 'mailing-audience-form']]) ?>
        <?= Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger mailing-form-errors', 'header' => '<strong>Не удалось сохранить аудиторию</strong>', 'encode' => false]) ?>

        <section class="mailing-form-section">
            <header class="mailing-form-section__head">
                <span class="mailing-step-number">1</span>
                <div><h2>Название</h2><p>Показывается только в админке и выборе аудитории.</p></div>
            </header>
            <?= $form->field($model, 'name', ['options' => ['class' => 'mailing-field']])->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'placeholder' => 'Например: VIP-пользователи сентября'])->label('Название аудитории') ?>
        </section>

        <section class="mailing-form-section">
            <header class="mailing-form-section__head">
                <span class="mailing-step-number">2</span>
                <div><h2>Пользователи</h2><p>Начните вводить запрос и добавьте нужных людей.</p></div>
            </header>
            <?= $form->field($model, 'ref_id', ['options' => ['class' => 'mailing-field']])->widget(Select2::class, [
                'data' => $model->getSelectedUsersOptions(),
                'value' => $model->getResolvedUserIds(),
                'options' => [
                    'multiple' => true,
                    'placeholder' => 'Введите минимум 2 символа…',
                ],
                'showToggleAll' => false,
                'pluginOptions' => [
                    'allowClear' => true,
                    'minimumInputLength' => 2,
                    'width' => '100%',
                    'ajax' => [
                        'url' => Url::to(['search-users']),
                        'dataType' => 'json',
                        'delay' => 250,
                        'data' => new JsExpression('function(params) { return {q: params.term}; }'),
                        'processResults' => new JsExpression('function(data) { return data; }'),
                    ],
                    'language' => [
                        'inputTooShort' => new JsExpression('function() { return "Введите ещё символы"; }'),
                        'noResults' => new JsExpression('function() { return "Пользователи не найдены"; }'),
                        'searching' => new JsExpression('function() { return "Поиск…"; }'),
                    ],
                ],
            ])->label('Участники аудитории')->hint('В список попадут ID пользователей. Перед каждой отправкой система проверит их доступность в выбранном канале.') ?>
        </section>

        <div class="mailing-form-actions">
            <?= Html::submitButton('<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Сохранить аудиторию', ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a('Отмена', $this->context->getIndexUrl(), ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    <?php ActiveForm::end() ?>
</div>

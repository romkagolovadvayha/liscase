<?php

use common\models\servers\ServersTags;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $selectedTags */

$selectedTags = $model->isNewRecord ? [] : $model->getTagIds();
?>

<div class="servers-form">
    <?php $form = ActiveForm::begin(); ?>

    <?= \yii\helpers\Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger', 'encode' => false]) ?>

    <!-- Основная информация -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Основная информация</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'name')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'tag')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'monitoring_name')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'monitoring_description')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'rust_app_id')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'wargm_id')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'sort')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'status')->dropDownList(
                        \common\models\servers\Servers::getStatusList(),
                        ['class' => 'form-control ds-input']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Вайп информация -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Вайп информация</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'wipe')->textInput(['class' => 'form-control ds-input', 'placeholder' => 'YYYY-MM-DD HH:MM:SS']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'next_wipe')->textInput(['class' => 'form-control ds-input', 'placeholder' => 'YYYY-MM-DD HH:MM:SS']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'global_wipe')->textInput(['class' => 'form-control ds-input', 'placeholder' => 'YYYY-MM-DD HH:MM:SS']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'wipe_type')->dropDownList(
                        [
                            7 => Yii::t('common', 'Еженедельно'),
                            14 => Yii::t('common', 'Каждые две недели'),
                            30 => Yii::t('common', 'Раз в месяц'),
                        ],
                        ['prompt' => 'Выберите тип...', 'class' => 'form-control ds-input']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Параметры карты -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Параметры карты</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'min_map_size')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'max_map_size')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'secret_map')->dropDownList(
                        [
                            0 => Yii::t('common', 'Нет'),
                            1 => Yii::t('common', 'Да'),
                        ],
                        ['class' => 'form-control ds-input']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключение к серверу -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Подключение к серверу</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'ip')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'port')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'query')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'rcon')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'rcon_password')->textInput(['maxlength' => true, 'class' => 'form-control ds-input', 'type' => 'password']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Игровые параметры -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Игровые параметры</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'max')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'team_limit')->textInput(['class' => 'form-control ds-input', 'type' => 'number']) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'skindrops')->dropDownList(
                        [
                            0 => Yii::t('common', 'Нет'),
                            1 => Yii::t('common', 'Да'),
                        ],
                        ['class' => 'form-control ds-input']
                    ) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'is_store')->dropDownList(
                        [
                            0 => Yii::t('common', 'Нет'),
                            1 => Yii::t('common', 'Да'),
                        ],
                        ['class' => 'form-control ds-input']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- API и интеграции -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">API и интеграции</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'secret_key')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'discord_token')->textInput(['class' => 'form-control ds-input']) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <?= $form->field($model, 'commands')->textInput(['class' => 'form-control ds-input', 'placeholder' => 'Команды через запятую']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Описание и правила -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Описание и правила</h5>
        </div>
        <div class="ds-card__body">
            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'description')->textarea(['rows' => 6, 'class' => 'form-control ds-input']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'rules')->textarea(['rows' => 6, 'class' => 'form-control ds-input']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Теги сервера -->
    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">Теги сервера</h5>
        </div>
        <div class="ds-card__body">
            <div class="form-group">
                <?= Select2::widget([
                    'name' => 'server_tags',
                    'value' => $selectedTags,
                    'data' => ServersTags::getTagsList(),
                    'options' => [
                        'placeholder' => Yii::t('common', 'Выберите теги...'),
                        'multiple' => true,
                        'class' => 'form-control',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'tags' => false,
                    ],
                ]); ?>
                <p class="help-block" style="color: var(--ds-text-secondary); margin-top: 0.5rem;">
                    <i class="bi bi-info-circle"></i> <?= Yii::t('common', 'Можно выбрать несколько тегов') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Кнопки действий -->
    <div class="ds-card">
        <div class="ds-card__body">
            <div class="ds-flex ds-items-center ds-gap-md">
                <?= Html::submitButton('<i class="bi bi-check-circle"></i> Сохранить', ['class' => 'ds-btn ds-btn--success']) ?>
                <?= Html::a('<i class="bi bi-x-circle"></i> Отмена', ['index'], ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

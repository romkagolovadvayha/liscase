<?php

use common\models\clan\Clan;
use common\models\servers\Servers;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Clan $model */
/** @var array<int, string> $leaderChoices */
?>

<div class="clan-form max-w-3xl p-4 lg:p-6">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => true,
        'id' => 'clan-form',
        'options' => ['class' => 'space-y-4'],
    ]); ?>

    <?php if ($model->isNewRecord): ?>
        <div class="ds-select-wrapper">
            <?= $form->field($model, 'server_id')->dropDownList(
                ArrayHelper::map(
                    Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
                    'id',
                    'name'
                ),
                ['class' => 'ds-select form-control', 'prompt' => Yii::t('common', '— выберите сервер —')]
            ) ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>

        <?= $form->field($model, 'leader_user_id')->input('number', [
            'class' => 'ds-input form-control',
            'min' => 1,
            'placeholder' => 'ID',
        ])->hint(Yii::t('common', 'ID пользователя-лидера из раздела «Игроки».')) ?>
    <?php endif; ?>

    <?= $form->field($model, 'name')->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

    <?= $form->field($model, 'tag')->textInput(['class' => 'ds-input form-control', 'maxlength' => true]) ?>

    <?= $form->field($model, 'motto')->textarea(['rows' => 2, 'class' => 'ds-textarea form-control']) ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 4, 'class' => 'ds-textarea form-control']) ?>

    <div class="ds-select-wrapper">
        <?= $form->field($model, 'privacy')->dropDownList([
            Clan::PRIVACY_OPEN => Yii::t('common', 'Открытый'),
            Clan::PRIVACY_CLOSED => Yii::t('common', 'Закрытый'),
            Clan::PRIVACY_INVITE_ONLY => Yii::t('common', 'По приглашению'),
        ], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?= $form->field($model, 'level')->input('number', ['class' => 'ds-input form-control', 'min' => 1]) ?>
        <?= $form->field($model, 'experience')->input('number', ['class' => 'ds-input form-control', 'min' => 0]) ?>
    </div>

    <?php if (!$model->isNewRecord): ?>
        <div class="ds-select-wrapper">
            <?= $form->field($model, 'leader_user_id')->dropDownList($leaderChoices, [
                'class' => 'ds-select form-control',
                'prompt' => Yii::t('common', '— выберите —'),
            ]) ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2 pt-2">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a(
            Yii::t('common', 'Отмена'),
            $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id],
            ['class' => 'ds-btn ds-btn--secondary']
        ) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

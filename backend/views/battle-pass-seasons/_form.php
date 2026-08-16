<?php

use common\models\battle_pass\BattlePassSeason;
use common\models\box\Drop;
use common\models\medals\Medal;
use common\models\tasks_v2\TaskV2;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var BattlePassSeason $model */

$this->title = $model->isNewRecord ? 'Создать сезон Battle Pass' : 'Изменить сезон Battle Pass';
$this->params['breadcrumbs'][] = ['label' => 'Сезоны Battle Pass', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="battle-pass-season-form p-4 lg:p-6">
    <div class="max-w-4xl bg-[hsl(0_0%_15%_/_1)] rounded-lg p-5">
        <?php $form = ActiveForm::begin(); ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <?= $form->field($model, 'name')->textInput(['class' => 'ds-input w-full']) ?>
            <?= $form->field($model, 'slug')->textInput(['class' => 'ds-input w-full']) ?>
            <?= $form->field($model, 'season_number')->textInput(['type' => 'number', 'min' => 1, 'class' => 'ds-input w-full']) ?>
            <?= $form->field($model, 'status')->dropDownList(BattlePassSeason::getStatusList(), ['class' => 'ds-select w-full']) ?>
            <?= $form->field($model, 'starts_at')->input('datetime-local', ['value' => $model->starts_at ? date('Y-m-d\TH:i', strtotime($model->starts_at)) : '', 'class' => 'ds-input w-full']) ?>
            <?= $form->field($model, 'ends_at')->input('datetime-local', ['value' => $model->ends_at ? date('Y-m-d\TH:i', strtotime($model->ends_at)) : '', 'class' => 'ds-input w-full']) ?>
        </div>
        <?= $form->field($model, 'description')->textarea(['rows' => 4, 'class' => 'ds-input w-full']) ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <?= $form->field($model, 'reward_type')->dropDownList(TaskV2::getRewardTypeList(), ['class' => 'ds-select w-full']) ?>
            <?= $form->field($model, 'reward_item_id')->widget(Select2::class, ['data' => Drop::getList(), 'options' => ['placeholder' => 'Выберите предмет']]) ?>
            <?= $form->field($model, 'reward_currency')->dropDownList(['personal' => 'Лицевой счёт', 'skins' => 'Скины'], ['class' => 'ds-select w-full']) ?>
            <?= $form->field($model, 'reward_amount')->textInput(['type' => 'number', 'step' => '0.01', 'class' => 'ds-input w-full']) ?>
            <?= $form->field($model, 'medal_id')->dropDownList(ArrayHelper::map(Medal::find()->where(['is_active' => 1])->all(), 'id', 'name'), ['prompt' => 'Выберите медаль', 'class' => 'ds-select w-full']) ?>
        </div>
        <div class="flex gap-2 mt-4">
            <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
            <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

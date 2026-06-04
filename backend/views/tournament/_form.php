<?php

use backend\forms\tournament\TournamentForm;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var TournamentForm $model */
?>

<div class="tournament-form max-w-4xl p-4 lg:p-6">
    <?php $form = ActiveForm::begin([
        'id' => 'tournament-form',
        'options' => ['enctype' => 'multipart/form-data', 'class' => 'space-y-4'],
    ]); ?>

    <?= $form->field($model, 'title')->textInput(['class' => 'ds-input form-control']) ?>
    <?= $form->field($model, 'slug')->textInput(['class' => 'ds-input form-control'])
        ->hint(Yii::t('common', 'Оставьте пустым для автогенерации')) ?>

    <div class="ds-select-wrapper">
        <?= $form->field($model, 'server_id')->dropDownList(
            ArrayHelper::map(
                Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
                'id',
                'name'
            ),
            ['class' => 'ds-select form-control', 'prompt' => Yii::t('common', '— сервер —')]
        ) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="ds-select-wrapper">
        <?= $form->field($model, 'status')->dropDownList([
            Tournament::STATUS_DRAFT => Yii::t('common', 'Черновик'),
            Tournament::STATUS_PUBLISHED => Yii::t('common', 'Опубликован'),
            Tournament::STATUS_ARCHIVED => Yii::t('common', 'В архиве'),
        ], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?= $form->field($model, 'starts_at')->input('datetime-local', ['class' => 'ds-input form-control']) ?>
        <?= $form->field($model, 'ends_at')->input('datetime-local', ['class' => 'ds-input form-control']) ?>
    </div>

    <?= $form->field($model, 'registration_ends_at')->input('datetime-local', ['class' => 'ds-input form-control']) ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?= $form->field($model, 'max_clans')->input('number', ['class' => 'ds-input form-control', 'min' => 1])
            ->hint(Yii::t('common', 'Пусто — без лимита')) ?>
        <?= $form->field($model, 'max_participants_per_clan')->input('number', ['class' => 'ds-input form-control', 'min' => 1])
            ->hint(Yii::t('common', 'Пусто — без лимита состава')) ?>
    </div>

    <?= $form->field($model, 'prize_pool_label')->textInput(['class' => 'ds-input form-control', 'placeholder' => '100 000 PRO']) ?>
    <?= $form->field($model, 'format_label')->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Raid-points']) ?>
    <?= $form->field($model, 'tags')->textInput(['class' => 'ds-input form-control'])
        ->hint(Yii::t('common', 'Через запятую: 5v5, Raid-points, PC')) ?>
    <?= $form->field($model, 'sort')->input('number', ['class' => 'ds-input form-control']) ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 3, 'class' => 'ds-textarea form-control']) ?>
    <?= $form->field($model, 'rules_text')->textarea(['rows' => 6, 'class' => 'ds-textarea form-control']) ?>

    <div class="mb-4">
        <?php if ($model->cover_image): ?>
            <p class="text-sm text-gray-400 mb-2"><?= Yii::t('common', 'Текущая обложка') ?>:</p>
            <img src="<?= Html::encode($model->getCoverUrl()) ?>" alt="" class="max-h-40 rounded mb-2" />
        <?php endif; ?>
        <?= $form->field($model, 'cover_file')->fileInput(['class' => 'ds-input form-control']) ?>
    </div>

    <h3 class="text-lg font-semibold text-white mb-3"><?= Yii::t('common', 'Награды (1–3 место)') ?></h3>
    <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="border border-[hsl(0_0%_20%)] rounded p-4 mb-3">
            <p class="text-white font-medium mb-2"><?= (int)($i + 1) ?> <?= Yii::t('common', 'место') ?></p>
            <?= $form->field($model, "reward_title[{$i}]")->textInput(['class' => 'ds-input form-control'])->label(Yii::t('common', 'Надпись')) ?>
            <?= $form->field($model, "reward_subtitle[{$i}]")->textInput(['class' => 'ds-input form-control'])->label(Yii::t('common', 'Подзаголовок')) ?>
            <?php
            $reward = null;
            if (!$model->isNewRecord) {
                foreach ($model->rewards as $r) {
                    if ((int)$r->place === $i + 1) {
                        $reward = $r;
                        break;
                    }
                }
            }
            if ($reward && $reward->getImageUrl()): ?>
                <img src="<?= Html::encode($reward->getImageUrl()) ?>" alt="" class="max-h-24 rounded mb-2" />
            <?php endif; ?>
            <?= $form->field($model, "reward_image_file[{$i}]")->fileInput(['class' => 'ds-input form-control'])->label(Yii::t('common', 'Картинка награды')) ?>
        </div>
    <?php endfor; ?>

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

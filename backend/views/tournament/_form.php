<?php

use backend\forms\tournament\TournamentForm;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var TournamentForm $model */

$fieldCompact = static function ($form, $model, $attr, $options = []) {
    return $form->field($model, $attr, array_merge([
        'options' => ['class' => 'mb-0'],
        'template' => '{label}{input}{error}{hint}',
    ], $options));
};
?>

<div class="tournament-form tournament-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'id' => 'tournament-form',
        'options' => ['enctype' => 'multipart/form-data', 'class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <div class="flex-1 min-w-0 p-4 lg:p-6 tournament-form-content space-y-3">
        <?= $fieldCompact($form, $model, 'title')->textInput(['class' => 'ds-input form-control']) ?>
        <?= $fieldCompact($form, $model, 'slug')->textInput(['class' => 'ds-input form-control'])
            ->hint(Yii::t('common', 'Пусто — автогенерация')) ?>
        <?= $fieldCompact($form, $model, 'description')->textarea(['rows' => 3, 'class' => 'ds-textarea form-control']) ?>
        <?= $fieldCompact($form, $model, 'rules_text')->textarea(['rows' => 8, 'class' => 'ds-textarea form-control']) ?>
    </div>

    <aside class="tournament-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('server_id') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $fieldCompact($form, $model, 'server_id', ['template' => '{input}{error}'])->dropDownList(
                                ArrayHelper::map(
                                    Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(),
                                    'id',
                                    'name'
                                ),
                                ['class' => 'ds-select w-full text-sm', 'prompt' => '—']
                            ) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $fieldCompact($form, $model, 'status', ['template' => '{input}{error}'])->dropDownList(
                                Tournament::getStatusList(),
                                ['class' => 'ds-select w-full text-sm']
                            ) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('starts_at') ?></label>
                            <?= $fieldCompact($form, $model, 'starts_at', ['template' => '{input}{error}'])->input('datetime-local', ['class' => 'ds-input w-full text-sm form-control']) ?>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('ends_at') ?></label>
                            <?= $fieldCompact($form, $model, 'ends_at', ['template' => '{input}{error}'])->input('datetime-local', ['class' => 'ds-input w-full text-sm form-control']) ?>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('registration_ends_at') ?></label>
                            <?= $fieldCompact($form, $model, 'registration_ends_at', ['template' => '{input}{error}'])->input('datetime-local', ['class' => 'ds-input w-full text-sm form-control']) ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('max_clans') ?></label>
                            <?= $fieldCompact($form, $model, 'max_clans', ['template' => '{input}{error}'])->input('number', ['class' => 'ds-input w-full text-sm form-control', 'min' => 1]) ?>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('max_participants_per_clan') ?></label>
                            <?= $fieldCompact($form, $model, 'max_participants_per_clan', ['template' => '{input}{error}'])->input('number', ['class' => 'ds-input w-full text-sm form-control', 'min' => 1]) ?>
                        </div>
                    </div>
                    <?= $fieldCompact($form, $model, 'prize_pool_label')->textInput(['class' => 'ds-input w-full text-sm form-control', 'placeholder' => '100 000 PRO']) ?>
                    <?= $fieldCompact($form, $model, 'format_label')->textInput(['class' => 'ds-input w-full text-sm form-control']) ?>
                    <?= $fieldCompact($form, $model, 'tags')->textInput(['class' => 'ds-input w-full text-sm form-control'])
                        ->hint(Yii::t('common', 'Через запятую')) ?>
                    <?= $fieldCompact($form, $model, 'sort')->input('number', ['class' => 'ds-input w-full text-sm form-control']) ?>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-2 uppercase tracking-wide"><?= Yii::t('common', 'Обложка') ?></h3>
                <?php if ($model->cover_image): ?>
                    <img src="<?= Html::encode($model->getCoverUrl()) ?>" alt="" class="max-h-24 rounded mb-2 w-full object-cover" />
                <?php endif; ?>
                <?= $fieldCompact($form, $model, 'cover_file', ['template' => '{input}{error}'])->fileInput(['class' => 'ds-input w-full text-sm form-control']) ?>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-white mb-2 uppercase tracking-wide"><?= Yii::t('common', 'Награды') ?></h3>
                <?php for ($i = 0; $i < 3; $i++): ?>
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
                    ?>
                    <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] p-2 mb-2 bg-[hsl(0_0%_18%_/_1)]">
                        <p class="text-xs text-gray-400 mb-1"><?= (int)($i + 1) ?> <?= Yii::t('common', 'место') ?></p>
                        <?= $fieldCompact($form, $model, "reward_title[{$i}]", ['template' => '{input}{error}'])->textInput([
                            'class' => 'ds-input w-full text-sm form-control',
                            'placeholder' => Yii::t('common', 'Надпись'),
                        ]) ?>
                        <?= $fieldCompact($form, $model, "reward_subtitle[{$i}]", ['template' => '{input}{error}'])->textInput([
                            'class' => 'ds-input w-full text-sm form-control mt-1',
                            'placeholder' => Yii::t('common', 'Подзаголовок'),
                        ]) ?>
                        <?php if ($reward && $reward->getImageUrl()): ?>
                            <img src="<?= Html::encode($reward->getImageUrl()) ?>" alt="" class="max-h-16 rounded my-1" />
                        <?php endif; ?>
                        <?= $fieldCompact($form, $model, "reward_image_file[{$i}]", ['template' => '{input}{error}'])->fileInput(['class' => 'ds-input w-full text-sm form-control mt-1']) ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="p-4 border-t border-[hsl(0_0%_15.3%_/_1)] flex flex-col gap-2">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary w-full justify-center']) ?>
            <?= Html::a(
                Yii::t('common', 'Отмена'),
                $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id],
                ['class' => 'ds-btn ds-btn--secondary w-full justify-center text-center']
            ) ?>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>

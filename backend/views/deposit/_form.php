<?php

use common\models\invoice\Deposit;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */
?>

<div class="deposit-form deposit-form--compact">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'deposit-form',
        'options' => ['class' => 'deposit-form-inner flex flex-col min-h-0 flex-1 w-full'],
    ]); ?>

    <div class="deposit-form-layout flex flex-col lg:flex-row min-h-0 flex-1">
        <!-- Основная колонка: карточка пользователя (если есть) + сумма -->
        <div class="flex-1 min-w-0 p-4 lg:p-6 deposit-form-content">
            <?php if ($model->user): ?>
            <div class="deposit-form-user-card">
                <div class="deposit-form-user-card__avatar">
                    <?php if ($avatarUrl = $model->user->getAvatar()): ?>
                        <img src="<?= Html::encode($avatarUrl) ?>" alt="" width="44" height="44" loading="lazy" />
                    <?php else: ?>
                        <span class="deposit-form-user-card__avatar-placeholder">?</span>
                    <?php endif; ?>
                </div>
                <div class="deposit-form-user-card__body">
                    <div class="deposit-form-user-card__name">
                        <?= Html::a(Html::encode($model->user->username), '/profile/' . $model->user_id, ['class' => 'deposit-form-user-card__link']) ?>
                    </div>
                    <a href="https://steamcommunity.com/profiles/<?= Html::encode($model->user->steam_id) ?>" target="_blank" rel="noopener" class="deposit-form-user-card__steam"><?= Html::encode($model->user->steam_id) ?></a>
                </div>
            </div>
            <?php endif; ?>

            <?= $form->field($model, 'amount', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control', 'type' => 'number', 'min' => 1]) ?>

            <div class="deposit-form-submit-desktop mt-3 flex flex-wrap gap-2 items-center">
                <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>

        <!-- Правая колонка: статус и метод оплаты -->
        <aside class="deposit-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] lg:h-full overflow-y-auto scrollbar-thin flex flex-col">
            <div class="p-4 flex-1 flex flex-col">
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                            <div class="ds-select-wrapper">
                                <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                                <i class="fas fa-chevron-down ds-select-arrow"></i>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('payment_type') ?></label>
                            <div class="ds-select-wrapper">
                                <?= $form->field($model, 'payment_type', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Deposit::getTypeList(), ['class' => 'ds-select w-full text-sm']) ?>
                                <i class="fas fa-chevron-down ds-select-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Кнопка «Сохранить» внизу на мобилке -->
    <div class="deposit-form-submit-mobile">
        <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary ds-btn--block']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<style>
.deposit-form-user-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: hsl(0 0% 15% / 1);
    border-radius: 10px;
    border: 1px solid hsl(0 0% 20% / 1);
    margin-bottom: 16px;
}
.deposit-form-user-card__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    background: hsl(0 0% 22% / 1);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.deposit-form-user-card__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.deposit-form-user-card__avatar-placeholder {
    color: hsl(0 0% 50%);
    font-size: 1.25rem;
}
.deposit-form-user-card__body { min-width: 0; }
.deposit-form-user-card__name { font-weight: 600; margin-bottom: 2px; }
.deposit-form-user-card__link { color: hsl(200 70% 60%); text-decoration: none; }
.deposit-form-user-card__link:hover { text-decoration: underline; }
.deposit-form-user-card__steam {
    font-size: 12px;
    color: hsl(0 0% 60%);
    text-decoration: none;
}
.deposit-form-user-card__steam:hover { text-decoration: underline; }

.deposit-form-submit-mobile { display: none; }
.deposit-form-submit-desktop { display: flex; }
@media (max-width: 991px) {
    .deposit-form-submit-desktop { display: none; }
    .deposit-form-submit-mobile {
        display: block;
        padding: 16px;
        padding-bottom: max(16px, env(safe-area-inset-bottom));
        background: hsl(0 0% 13% / 1);
        border-top: 1px solid hsl(0 0% 18% / 1);
    }
    .deposit-form-submit-mobile .ds-btn {
        min-height: 48px;
        width: 100%;
        justify-content: center;
    }
}
</style>

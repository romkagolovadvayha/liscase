<?php
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;
use yii\helpers\Html;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var array $item */
/** @var \frontend\forms\user\SkinsForm $formModel */
/** @var integer $balance */
?>

<?php Pjax::begin([
    'id' => 'skin-confirm-pjax',
    'enablePushState' => false,
    'timeout' => 10000,
]); ?>

<?php $form = ActiveForm::begin(
    [
        'id'                     => 'skin-confirm',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative">
        <div style="display: none"><?=$form->field($formModel, 'id')->label(false)->hiddenInput(['value' => $item['id']])?></div>
        <div style="display: none"><?=$form->field($formModel, 'amount')->label(false)->hiddenInput(['value' => $item['price']])?></div>
        
        <div class="text-center mb-24">
            <img src="<?=Html::encode($item['image'])?>" alt="<?=Html::encode($item['ru_name'] ?? $item['name'])?>" style="max-width: 200px; max-height: 200px; margin: 0 auto 16px; display: block;">
            <h3 class="mb-12" style="font-size: 18px; font-weight: 600; color: var(--text-main);">
                <?=Html::encode($item['ru_name'] ?? $item['name'])?>
            </h3>
            <?php 
            // Для Rust показываем переведенный ru_quality (тип предмета), для CS2 - ru_quality (качество)
            $qualityDisplay = '';
            if (!empty($item['ru_quality'])) {
                if (isset($type) && $type == 'rust') {
                    // Для Rust используем справочник переводов
                    $qualityDisplay = \common\components\rusttm\RustTm::translateItemType($item['ru_quality']);
                } else {
                    // Для CS2 показываем как есть
                    $qualityDisplay = $item['ru_quality'];
                }
            }
            if ($qualityDisplay): ?>
                <p class="mb-16" style="font-size: 14px; color: var(--system-colors-success-color);">
                    <?=Html::encode($qualityDisplay)?>
                </p>
            <?php endif; ?>
            <p class="mb-16" style="font-size: 20px; font-weight: 700; color: var(--text-main);">
                <?=number_format($item['price'], 0, '.', ' ')?>
                <span class="icons icons_16px icons_16px_coin_skins"></span>
            </p>
        </div>
        
        <p class="mb-24 p1" style="text-align: center; color: var(--text-secondary);">
            <?=Yii::t('common', 'Вы уверены, что хотите купить этот скин за {price} монет?', [
                'price' => '<strong>' . number_format($item['price'], 0, '.', ' ') . '</strong>'
            ])?>
        </p>
        
        <div class="p3" style="background: var(--background-teritiary); padding: 16px; border-radius: var(--card-radius); border: 1px solid var(--border-color-default); color: var(--text-secondary); text-align: center;">
            <i class="fas fa-info-circle" style="margin-right: 8px; color: var(--primary-colors-secondary);"></i>
            <?=Yii::t('common', 'В случае если вы не примите обмен или не придет трейд, деньги автоматически вернутся на баланс в течение часа.')?>
        </div>
    </div>
</div>

<footer class="px-24 pb-24">
    <?= Alert::widget() ?>
    <div style="display: flex; gap: 12px;">
        <button type="button" class="button-secondary w-full" data-bs-dismiss="modal">
            <span class="button__text"><?=Yii::t('common', 'Отмена')?></span>
        </button>
        <button type="submit" id="buy_product" class="button-primary w-full">
            <span class="button__text"><?=Yii::t('common', 'Получить')?></span>
        </button>
    </div>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>

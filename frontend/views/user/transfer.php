<?php
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var $transferForm */

$url = Yii::$app->request->url;
?>

<?php $form = ActiveForm::begin(
    [
        'id'                     => 'transfer',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative">
        <div style="display: none"><?=$form->field($transferForm, 'type')->label(false)->hiddenInput()?></div>
        <p class="mb-24 p1">
            <?=Yii::t('common', 'Пожалуйста, подтвердите перевод средств на счёт магазина. После подтверждения возврат средств станет невозможным.')?>
        </p>
        <div class="relative">
            <?=$form->field($transferForm, 'amount', [
                'inputOptions' => [
                    'class' => 'search search_pay'
                ],
                'template' => "{input}<span class=\"icons icons_16px icons_16px_coin\"></span>{error}"
            ])
                ->label(false)
                ->textInput(['placeholder' => Yii::t('common', 'Введите сумму перевода')]); ?>
        </div>
    </div>
</div>

<footer class="px-24 pb-24">
    <?= Alert::widget() ?>
    <button type="submit" id="buy_product" class="button-primary w-full">
        <span class="button__text"><?=Yii::t('common', 'Подтвердить')?></span>
    </button>
</footer>
<?php ActiveForm::end(); ?>
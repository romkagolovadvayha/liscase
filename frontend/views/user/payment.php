<?php

use common\models\user\User;
use yii\web\View;
use common\models\invoice\Deposit;
use frontend\forms\market\PaymentForm;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use yii\bootstrap5\Html;
use common\models\invoice\PaymentBonuses;

/** @var View $this */
/** @var PaymentForm $modelForm */
/** @var PaymentBonuses[] $bonuses */
//payments__payment-btn--active
$user        = Yii::$app->user->identity;
$this->title = Yii::t('common', "Пополнения баланса");
/** @var User $user */
$user = Yii::$app->user->identity;
?>

<?php Pjax::begin(
    [
        'id'              => 'payment-balance-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'payment-balance-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
<?= Alert::widget() ?>
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative payment-form z-1 grid gap-y-8">
        <div class="relative mt-12">
            <?=$form->field($modelForm, 'amount', [
                'inputOptions' => [
                    'class' => 'search search_pay'
                ],
                'template' => "{input}<span class=\"icons icons_16px icons_16px_coin\"></span>{error}{hint}"
            ])
                    ->label(false)
                    ->textInput(['placeholder' => Yii::t('common', 'Введите сумму пополнения'), 'autocomplete' => 'off']); ?>
        </div>
        <?php if (!$user->is_email): ?>
            <div class="relative">
                <?=$form->field($modelForm, 'email', [
                    'inputOptions' => [
                        'class' => 'search search_pay'
                    ],
                ])
                        ->label(false)
                        ->textInput(['placeholder' => Yii::t('common', 'Ваш E-mail')]); ?>
            </div>
        <?php endif; ?>

        <div class="pay__list mb-32">
            <?php foreach ($bonuses as $bonus): ?>
                <!--pay__button_active-->
                <button type="button" class="pay__button" data-value="<?=$bonus->amount?>">
                    <span class="text-text-main p3"><?=Yii::t('common', 'от')?> <?=number_format($bonus->amount, 0, '.', ' ')?> ₽</span>
                    <span class="text-link-color-default p3">+<?=$bonus->bonus?>%</span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="mb-16">
            <?= $form->field($modelForm, 'payment_id')
                     ->radioList(Deposit::getIconTypeList(), [
                         'item' => function ($index, $label, $name, $checked, $value) use ($modelForm) {
                             $id = 'option_' . $index . '_' . $value;
                             $return = Html::radio($name, $value == $modelForm->payment_id, [
                                 'id'    => $id,
                                 'value' => $value,
                                 'class' => 'pay-card__button-radio',
                                 'style' => 'position: absolute; left: 0; bottom: 0; width: 1px; height: 1px; opacity: 0;',
                             ]);
                             $img = Html::img($label, [
                                 'style' => 'max-width: 90%;max-height: 36px;',
                             ]);
                             $shortNameList = Deposit::getShortNameList();
                             $shortName = "";
                             if (!empty($shortNameList[$value])) {
                                 $shortName = Html::tag('div', $shortNameList[$value], [
                                     'class' => 'text-text-teritiary',
                                     'style' => 'font-size: 10px',
                                 ]);
                             }
                             $return .= Html::label($img . $shortName, $id, [
                                 'class' => 'pay-card__button',
                                 'data-card-value' => 'mc_visa_mir',
                             ]);
                             return Html::tag('div', $return);
                         },
                         'class' => 'pay-card__list'
                     ])
                     ->label(false); ?>
        </div>

        <?php
        $labelTemplate = Yii::t('common', 'Я принимаю условия {param_translate_rules}', [
            'param_translate_rules' => Html::a(Yii::t('common', 'пользовательского соглашения'), ['/site/agreement'], ['target' => '_blank', 'class' => 'p1', 'data-pjax' => '0'])
        ]);
        $checkboxTemplate = '<label class="pay__conditions">{input}<span><span class="icons icons_24px icons_24px_checkbox"></span><span class="icons icons_24px icons_24px_checkbox_outline"></span></span><p class="p1" style="width: 80%">' . $labelTemplate . '</p>{error}{hint}</label>';
        $options = [
            'class' => 'pay-checkbox none',
            'value' => 1
        ];
        if ($modelForm->confirm) {
            $options['checked'] = 'checked';
        }
        ?>
        <div class="none"><?= $form->field($modelForm, 'confirm')->hiddenInput(['value' => 0]); ?></div>
        <?= $form->field($modelForm, 'confirm', [
            'template' => $checkboxTemplate,
        ])->input('checkbox', $options); ?>
    </div>
</div>

<footer class="px-24 pb-24">
    <button type="submit" id="buy_product" class="button-primary w-full">
        <span class="button__text"><?=Yii::t('common', 'Перейти к оплате')?></span>
    </button>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="page_preloader" id="product-loader"></div>
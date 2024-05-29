<?php

use yii\web\View;
use common\models\invoice\Deposit;
use frontend\forms\market\PaymentForm;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use yii\bootstrap5\Html;

/** @var View $this */
/** @var PaymentForm $modelForm */
//payments__payment-btn--active
$user        = Yii::$app->user->identity;
$this->title = Yii::t('common', "Пополнения баланса");
?>

<?php Pjax::begin(
    [
        'id'              => 'payment-balance-container-pjax',
        'enablePushState' => false
    ]
); ?>
<?= Alert::widget() ?>
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
<?= $form->field($modelForm, 'amount', [
    'template' => "{label}\n<div class=\"input-group input-group-custom\">{input}\n<span class=\"input-group-text\">".Yii::t('common', 'RUB')."\n{hint}\n{error}</span></div>"
])->label(false)->textInput(['placeholder' => Yii::t('common', 'Введите сумму пополнения'), 'autocomplete' => 'off']); ?>
<div class="payments">
    <div class="payments-list">
        <?= $form->field($modelForm, 'payment_id')
                 ->radioList(Deposit::getIconTypeList(), [
                     'item' => function ($index, $label, $name, $checked, $value) use ($modelForm) {
                         $id = 'option_' . $index . '_' . $value;
                         $return = Html::radio($name, $value == $modelForm->payment_id, [
                             'id'    => $id,
                             'value' => $value,
                             'class' => 'payments__payment-radio',
                         ]);
                         $img = Html::img($label, [
                             'class' => 'payments__payment-icon',
                         ]);
                         $imgWrap = Html::tag('div', $img, [
                             'class' => 'payments__payment-btn',
                         ]);
                         $shortNameList = Deposit::getShortNameList();
                         $shortName = "";
                         if (!empty($shortNameList[$value])) {
                             $shortName = Html::tag('div', $shortNameList[$value], [
                                 'class' => 'payments__payment-name',
                             ]);
                         }
                         $return .= Html::label($imgWrap . $shortName, $id, [
                             'class' => 'payments__payment'
                         ]);
                         return $return;
                     },
                 ])
                 ->label(false); ?>
    </div>
</div>
<div class="widget_bonus">
    <div class="widget_bonus_item">
        <span class="widget_bonus_item_sum">от 500 RUB</span>
        <span class="widget_bonus_item_percent">+15%</span>
    </div>
    <div class="widget_bonus_item">
        <span class="widget_bonus_item_sum">от 1000 RUB</span>
        <span class="widget_bonus_item_percent">+20%</span>
    </div>
    <div class="widget_bonus_item">
        <span class="widget_bonus_item_sum">от 1500 RUB</span>
        <span class="widget_bonus_item_percent">+25%</span>
    </div>
    <div class="widget_bonus_item">
        <span class="widget_bonus_item_sum">от 2000 RUB</span>
        <span class="widget_bonus_item_percent">+30%</span>
    </div>
    <div class="widget_bonus_item">
        <span class="widget_bonus_item_sum">от 5000 RUB</span>
        <span class="widget_bonus_item_percent">+50%</span>
    </div>
</div>
<?= $form->field($modelForm, 'confirm', [
    'template'              => '{input}{label}{hint}',
])->label(Yii::t('common', 'Я принимаю условия {param_translate_rules}',
                 ['param_translate_rules' => Html::a(Yii::t('common', 'пользовательского соглашения'),
                                                     ['/site/agreement'], [
                                                         //                        'class'       => 'new-tab-link',
                                                         //                        'data-target' => '#modal-dialog',
                                                         //                        'data-size'   => 'modal-lg',
                                                         //                        'data-toggle' => 'modal',
                                                         'target' => '_blank',
                                                         'data-pjax' => '0'
                                                     ])
                 ]), ['class' => 'form-check-label'])
         ->checkbox(['class' => 'form-check-input'], false) ?>
<button type="submit" class="btn"><?=Yii::t('common', 'Перейти к оплате')?></button>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>

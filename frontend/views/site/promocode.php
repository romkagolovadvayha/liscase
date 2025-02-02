<?php
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */

$url = Yii::$app->request->url;
$promocodeForm = new \frontend\forms\promocode\PromocodeForm();
?>

<?php Pjax::begin(
    [
        'id'              => 'promocode-pjax',
        'enablePushState' => false
    ]
); ?>
<?php $form = ActiveForm::begin(
    [
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'promocode',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]
); ?>
    <div class="grid gap-y-24 px-24 mb-24">
        <div class="relative">
            <?=$form->field($promocodeForm, 'code', [
                    'inputOptions' => [
                        'class' => 'search search_pay'
                    ],
                    'template' => "{input}<span class=\"icons icons_16px icons_16px_coin\"></span>"
            ])
                    ->label(false)
                    ->textInput(['placeholder' => Yii::t('common', 'Введите промокод')]); ?>
        </div>
    </div>

    <footer class="px-24 pb-24">
        <?= Alert::widget() ?>
        <button type="submit" id="buy_product" class="button-primary w-full">
            <span class="button__text"><?=Yii::t('common', 'Применить')?></span>
        </button>
    </footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>
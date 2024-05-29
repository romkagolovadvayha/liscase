<?php
use yii\widgets\ActiveForm;
use common\models\promocode\Promocode;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

/** @var yii\web\View $this */

$url = Yii::$app->request->url;
$promocodeForm = new \frontend\forms\promocode\PromocodeForm();
?>

<div class="promocode">
    <?php Pjax::begin(
        [
            'id'              => 'promocode-pjax',
            'enablePushState' => false
        ]
    ); ?>
    <?= Alert::widget() ?>
    <?php $form = ActiveForm::begin(
        [
            'enableClientValidation' => false,
            'enableAjaxValidation'   => false,
            'id'                     => 'promocode',
            'action'                 => \yii\helpers\Url::to('/'),
            'options'                => [
                'data-pjax' => 1,
            ],
        ]
    ); ?>
    <?= $form->field($promocodeForm, 'code', [
        'template' => "{label}\n<div class=\"input-group\">{input}\n<span class=\"input-group-btn\"><button type=\"submit\" class=\"btn\">".Yii::t('common', 'Применить')."</button>\n{hint}\n{error}</span></div>"
    ])->label(false)->textInput(['placeholder' => Yii::t('common', 'Введите промокод')]); ?>
    <?php ActiveForm::end(); ?>
    <?php Pjax::end(); ?>
</div>
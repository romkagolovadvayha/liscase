<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var \frontend\forms\serverskin\ServerSkinForm $model */
/** @var yii\widgets\ActiveForm $form */
/** @var \common\models\servers\Servers $server */

?>

<?php Pjax::begin(['id' => 'create-form-pjax', 'enablePushState' => false]); ?>
<?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation'   => false,
        'id'                     => 'create-form-container',
        'options'                => [
            'data-pjax' => 1,
        ],
    ]); ?>
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative payment-form z-1 grid gap-y-8">
    <?= $form->field($model, 'steam_link')->textInput(['maxlength' => true]) ?>
    </div>
</div>

<footer class="px-24 pb-24">
    <button type="submit" id="buy_product" class="button-primary w-full">
        <span class="button__text"><?=Yii::t('common', 'Отправить на модерацию')?></span>
    </button>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<?php

use yii\bootstrap5\ActiveForm;
use backend\forms\news\NewsForm;

/** @var NewsForm $model */

$this->params['breadcrumbs'][] = [
    'label' => 'Список новостей',
    'url'   => $this->context->getIndexUrl(),
];

$this->params['breadcrumbs'][] = [
    'label' => $this->title,
];

?>

<?php $form = ActiveForm::begin([
    'id'             => 'news-form',
    'validateOnBlur' => false,
    'options'        => [
        'enctype' => 'multipart/form-data',
    ],
]); ?>

<div class="row">
    <div class="col-lg-4 col-md-8 col-sm-12 col-xs-12">
        <?= $form->field($model, 'name')->textInput(); ?>
        <div>&nbsp;</div>
    </div>
</div>

<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>

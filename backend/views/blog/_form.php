<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\JsExpression;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */
/** @var yii\widgets\ActiveForm $form */

?>

<div class="blog-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput() ?>

    <?= $form->field($model, 'keywords')->textInput() ?>
    <?= $form->field($model, 'description')->widget(\dosamigos\tinymce\TinyMce::className(), [
        'options' => ['rows' => 3],
        'language' => 'ru',
        'clientOptions' => [
            'plugins' => [
                "advlist autolink lists link charmap print preview anchor",
                "searchreplace visualblocks code fullscreen",
                "insertdatetime media table contextmenu paste"
            ],
            'toolbar' => "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image"
        ]
    ]);?>

    <?= $form->field($model, 'content')->widget(\dosamigos\tinymce\TinyMce::className(), [
        'options' => ['rows' => 12],
        'language' => 'ru',
    ]);?>
    <?php if (!empty($model->link_name)): ?>
        <?= $form->field($model, 'link_name')->textInput() ?>
    <?php endif; ?>

    <?= $form->field($model, 'blog_category_id')->dropDownList(\common\models\blog\BlogCategory::getChildsCategories(), [
        'prompt' => Yii::t('common', 'Не выбрано...'),
    ]) ?>

    <?= $form->field($model, 'status')->dropDownList(\common\models\blog\BlogCategory::getStatusList()) ?>

    <?=$this->render('list-images', [
            'blogId' => $model->id,
        ])?>

    <div class="form-group">
        <a href="/blog-image/create?blogId=<?=$model->id?>" class="btn btn-primary show-modal-link"
           data-toggl="modal"
           data-target="modal-dialog"
           data-title="Загрузить изображение">Загрузить изображение</a>
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

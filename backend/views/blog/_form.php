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
    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <?= $form->field($model, 'content')->widget(\dosamigos\tinymce\TinyMce::className(), [
        'options' => [
            'rows' => 12,
        ],
        'language' => 'ru',
        'clientOptions' => [
            'plugins' => [
                //  "advlist",
                // "paste"
                'emoticons',
                'link',
                'code',
            ],
            'menubar' => false,
            'resize' => false,
            'statusbar' => false,
            'paste_as_text' => true,
            'toolbar' => "undo redo bold italic emoticons link  | code",
            'default_link_target' => '_blank',
            'link_context_toolbar' => true
        ],
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

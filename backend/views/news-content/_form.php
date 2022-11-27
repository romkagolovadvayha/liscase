<?php

use yii\helpers\Url;
use yii\bootstrap5\ActiveForm;
use backend\forms\news\NewsContentForm;

/** @var NewsContentForm $model */

/** @var \common\models\news\News $news */
$news = $this->context->getNews();

$model->news_id = $news->id;

$languageList = $model->getLanguageList();

$this->params['breadcrumbs'][] = [
    'label' => 'Список новостей',
    'url'   => Url::toRoute(['/news/index']),
];

$this->params['breadcrumbs'][] = [
    'label' => $news->name,
    'url'   => Url::toRoute(['/news-content/index', 'newsId' => $news->id]),
];

$this->params['breadcrumbs'][] = [
    'label' => $this->title,
];

?>

<?php $form = ActiveForm::begin([
    'id'                   => 'news-content-form',
    'validateOnBlur'       => false,
    'options'              => [
        'enctype' => 'multipart/form-data',
    ],
]); ?>

<div class="row">
    <div class="col-lg-4 col-md-6 col-xs-12">
        <?= $form->field($model, 'news_id')->label(false)->hiddenInput(); ?>

        <?= $form->field($model, 'language')->dropDownList($model->getLanguageList()); ?>


        <?= $form->field($model, 'title')->textInput(); ?>
        <?= $form->field($model, 'title_text')->textInput(); ?>
        <?= $form->field($model, 'body')->textarea(); ?>
    </div>
</div>

<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>

<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;

/** @var yii\web\View $this */
/** @var \frontend\forms\buildings\BuildingForm $model */
/** @var yii\widgets\ActiveForm $form */

$formatJs = <<< 'JS'
var formatRepo = function (repo) {
    if (repo.loading) {
        return repo.text;
    }
    var markup =
'<a href="' + repo.statsLink + '" class="select2_dropdown_item">' + 
    '<div class="select2_dropdown_item_image"><img src="' + repo.avatar + '"/></div>' +
    '<div class="select2_dropdown_item_content">' +
    '<div class="select2_dropdown_item_content_name">' + repo.name + '</div>' +
    '<div class="select2_dropdown_item_content_steam_id">' + repo.steam_id + '</div>' +
    '</div>' +
'</a>';
    return '<div style="overflow:hidden;">' + markup + '</div>';
};
var formatRepoSelection = function (repo) {
    if (!repo.name) {
        return repo.text;
    }
    return '<div class="select2_dropdown_item">' + 
    '<div class="select2_dropdown_item_image_24"><img src="' + repo.avatar + '"/></div>' +
    '<div class="select2_dropdown_item_content">' +
    '<div class="select2_dropdown_item_content_name">' + repo.name + '</div>' +
    '</div>' +
'</a>';
}
JS;
$this->registerJs($formatJs, View::POS_HEAD);

$resultsJs = <<< JS
function (data, params) {
    return {
        results: data.items
    };
}
JS;

?>

<div class="building_form">

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'description')->textarea(['maxlength' => true, 'rows' => 3]) ?>

    <div class="building_form_residents">
        <?=$form->field($model, 'residents')->widget(Select2::CLASS, [
            'options' => ['multiple' => true, 'placeholder' => Yii::t('common', 'Введите ник или Steam ID...')],
            'pluginOptions' => [
                'allowClear' => true,
                'minimumInputLength' => 1,
                'ajax' => [
                    'url' => "/stats/search?server=" . Yii::$app->params['statisticsServerDefault'],
                    'dataType' => 'json',
                    'delay' => 250,
                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                    'processResults' => new JsExpression($resultsJs),
                    'cache' => true
                ],
                'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
                'templateResult' => new JsExpression('formatRepo'),
                'templateSelection' => new JsExpression('formatRepoSelection')
            ],
        ]);?>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'server_tag')->dropDownList(\common\models\servers\Servers::getServers(), [
                'prompt' => Yii::t('common', 'Не выбрано...'),
            ]) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'location')->textInput(['maxlength' => true]) ?>
        </div>
    </div>

    <?=$form->field($model, 'image[]')->widget(FileInput::CLASS, [
        'model' => $model,
        'options' => ['multiple' => true, 'accept' => 'image/png, image/gif, image/jpeg'],
        'language' => 'ru',
        'pluginOptions' => [
            'showPreview' => true,
            'showCaption' => false,
            'showRemove' => false,
            'showUpload' => false,
            'maxFileCount' => 5,
            'browseIcon' => '<i class="fas fa-camera"></i> ',
            'browseLabel' =>  Yii::t('common', 'Выберите несколько фотографий базы')
        ]
    ]);?>

    <div class="form-group">
        <button type="submit" class="button-primary" style="margin-right: 8px">
            <span class="button__text"><?=Yii::t('common', 'Отправить на модерацию')?></span>
        </button>
    </div>

    <?php ActiveForm::end(); ?>

</div>

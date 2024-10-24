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
'<a href="/stats/player?steamId=' + repo.steam_id + '&server=' + repo.server + '" class="">' + 
    '<div class="page_stats_search_name">' + repo.name + '</div>' +
    '<div class="page_stats_search_steam_id">' + repo.steam_id + '</div>' +
'</a>';
    return '<div style="overflow:hidden;">' + markup + '</div>';
};
var formatRepoSelection = function (repo) {
    return repo.name || repo.text;
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
                    'url' => "/stats/search?server=max3",
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
            'showPreview' => false,
            'showCaption' => true,
            'showRemove' => false,
            'showUpload' => false,
            'maxFileCount' => 5,
            'browseIcon' => '<i class="fas fa-camera"></i> ',
            'browseLabel' =>  'Выберите фото базы'
        ]
    ]);?>

    <div class="form-group">
        <?= Html::submitButton('Добавить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

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
/** @var \common\models\servers\Servers $server */

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

<div class="building-form">
    <?php $form = ActiveForm::begin([
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'building-form_form'
        ]
    ]); ?>

    <div class="building-form_grid">
        <!-- Левая колонка -->
        <div class="building-form_main">
            <!-- Основная информация -->
            <div class="form-section">
                <h3 class="form-section_title">
                    <i class="fa-solid fa-pen"></i>
                    <?= Yii::t('common', 'Информация о постройке') ?>
                </h3>
                
                <div class="form-field">
                    <label class="form-label">
                        <?= Yii::t('common', 'Название базы') ?>
                        <span class="form-label_required">*</span>
                    </label>
                    <?= $form->field($model, 'name', [
                        'template' => '{input}{error}',
                        'options' => ['class' => '']
                    ])->textInput([
                        'maxlength' => true,
                        'placeholder' => Yii::t('common', 'Например: Непробиваемая крепость'),
                        'class' => 'form-input'
                    ]) ?>
                </div>

                <div class="form-field">
                    <label class="form-label">
                        <?= Yii::t('common', 'Описание') ?>
                        <span class="form-label_required">*</span>
                    </label>
                    <?= $form->field($model, 'description', [
                        'template' => '{input}{error}',
                        'options' => ['class' => '']
                    ])->textarea([
                        'maxlength' => true,
                        'rows' => 5,
                        'placeholder' => Yii::t('common', 'Расскажите о своей постройке: сколько ресурсов потратили, особенности дизайна, время строительства...'),
                        'class' => 'form-textarea'
                    ]) ?>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-field">
                        <label class="form-label">
                            <?= Yii::t('common', 'Сервер') ?>
                            <span class="form-label_required">*</span>
                        </label>
                        <?= $form->field($model, 'server_tag', [
                            'template' => '{input}{error}',
                            'options' => ['class' => '']
                        ])->dropDownList(\common\models\servers\Servers::getServers(), [
                            'prompt' => Yii::t('common', 'Выберите сервер...'),
                            'class' => 'form-select'
                        ]) ?>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">
                            <?= Yii::t('common', 'Квадрат на карте') ?>
                            <span class="form-label_required">*</span>
                        </label>
                        <?= $form->field($model, 'location', [
                            'template' => '{input}{error}',
                            'options' => ['class' => '']
                        ])->textInput([
                            'maxlength' => true,
                            'placeholder' => 'E14',
                            'class' => 'form-input form-input--location'
                        ]) ?>
                    </div>
                </div>
            </div>

            <!-- Изображения -->
            <div class="form-section">
                <h3 class="form-section_title">
                    <i class="fa-solid fa-images"></i>
                    <?= Yii::t('common', 'Скриншоты') ?>
                </h3>
                
                <div class="form-field">
                    <?= $form->field($model, 'image[]', [
                        'template' => '{input}{error}',
                        'options' => ['class' => '']
                    ])->widget(FileInput::class, [
                        'model' => $model,
                        'options' => [
                            'multiple' => true, 
                            'accept' => 'image/png, image/gif, image/jpeg'
                        ],
                        'language' => 'ru',
                        'pluginOptions' => [
                            'showPreview' => true,
                            'showCaption' => false,
                            'showRemove' => true,
                            'showUpload' => false,
                            'maxFileCount' => 5,
                            'browseIcon' => '<i class="fas fa-camera"></i> ',
                            'browseLabel' => Yii::t('common', 'Выбрать скриншоты'),
                            'removeIcon' => '<i class="fas fa-trash"></i>',
                            'removeLabel' => '',
                            'previewFileType' => 'image',
                            'allowedFileExtensions' => ['jpg', 'jpeg', 'png', 'gif'],
                            'initialPreviewAsData' => true,
                            'overwriteInitial' => false,
                            'layoutTemplates' => [
                                'main1' => '<div class="file-input-custom">{preview}{browse}</div>',
                            ],
                        ]
                    ]) ?>
                    <div class="form-hint form-hint--info">
                        <i class="fa-solid fa-info-circle"></i>
                        <?= Yii::t('common', 'Максимум 5 изображений в форматах JPG, PNG или GIF') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Правая колонка -->
        <div class="building-form_sidebar">
            <!-- Подсказки -->
            <div class="form-tips">
                <h4 class="form-tips_title">
                    <i class="fa-solid fa-lightbulb"></i>
                    <?= Yii::t('common', 'Советы') ?>
                </h4>
                <ul class="form-tips_list">
                    <li><?= Yii::t('common', 'Делайте скриншоты в хорошем качестве') ?></li>
                    <li><?= Yii::t('common', 'Покажите разные ракурсы базы') ?></li>
                    <li><?= Yii::t('common', 'Укажите всех жильцов для полноты информации') ?></li>
                    <li><?= Yii::t('common', 'Опишите интересные детали постройки') ?></li>
                </ul>
            </div>

            <!-- Жильцы -->
            <div class="form-section form-section--compact">
                <h3 class="form-section_title">
                    <i class="fa-solid fa-users"></i>
                    <?= Yii::t('common', 'Жильцы') ?>
                </h3>
                
                <div class="form-field">
                    <?= $form->field($model, 'residents', [
                        'template' => '{input}{error}',
                        'options' => ['class' => '']
                    ])->widget(Select2::class, [
                        'options' => [
                            'multiple' => true, 
                            'placeholder' => Yii::t('common', 'Поиск...'),
                            'class' => 'form-select2'
                        ],
            'pluginOptions' => [
                'allowClear' => true,
                'minimumInputLength' => 1,
                'ajax' => [
                    'url' => "/stats/search?serverId=" . $server->id,
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
                    ]) ?>
                    <div class="form-hint form-hint--small">
                        <i class="fa-solid fa-search"></i>
                        <?= Yii::t('common', 'Введите ник или Steam ID') ?>
                    </div>
                </div>
    </div>

            <!-- Модерация -->
            <div class="form-moderation">
                <div class="form-moderation_icon">
                    <i class="fa-solid fa-shield-check"></i>
                </div>
                <div class="form-moderation_content">
                    <h4><?= Yii::t('common', 'Модерация') ?></h4>
                    <p><?= Yii::t('common', 'Ваша постройка будет проверена в течение 24 часов') ?></p>
                </div>
        </div>
        </div>
    </div>

    <!-- Кнопка отправки -->
    <div class="form-submit">
        <button type="submit" class="button button-primary button-submit">
            <span class="button__text"><?= Yii::t('common', 'Отправить на модерацию') ?></span>
        </button>
        
        <?= Html::a(
            Yii::t('common', 'Отмена'),
            ['index'],
            ['class' => 'button button-secondary']
        ) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

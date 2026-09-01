<?php

use backend\components\SettingsCatalog;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

/** @var \common\models\site\SiteSetting $model */
/** @var bool $isUpdate */
/** @var bool $hasStoredSecret */

$categoryOptions = [];
foreach (SettingsCatalog::navigation() as $group) {
    foreach ($group['categories'] as $category) {
        $categoryOptions[$category['code']] = $category['label'];
    }
}
$isSecret = SettingsCatalog::isSensitive($model) || $model->type === 'password';
?>

<div class="admin-form-page settings-editor-page">
    <header class="admin-form-page__header">
        <div>
            <p class="admin-form-page__eyebrow">Настройки</p>
            <h1><?= $isUpdate ? 'Редактирование параметра' : 'Новый параметр' ?></h1>
            <p>Название видит администратор, а категория и системный код используются в приложении.</p>
        </div>
        <?= Html::a(
            '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>К разделу</span>',
            ['/settings/index', 'category' => $model->category ?: 'site'],
            ['class' => 'ds-btn ds-btn--secondary']
        ) ?>
    </header>

    <?php $form = ActiveForm::begin([
        'options' => ['class' => 'admin-form-card'],
        'fieldConfig' => [
            'options' => ['class' => 'admin-form-field'],
            'labelOptions' => ['class' => 'admin-form-field__label'],
            'errorOptions' => ['class' => 'admin-form-field__error'],
            'hintOptions' => ['class' => 'admin-form-field__hint'],
        ],
    ]); ?>

    <?= \frontend\widgets\Alert::widget() ?>

    <fieldset class="admin-form-section">
        <legend>Описание</legend>
        <?= $form->field($model, 'name')
            ->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'autofocus' => true])
            ->hint('Короткое понятное название для администраторов.') ?>

        <?= $form->field($model, 'category')
            ->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'list' => 'settings-category-options', 'spellcheck' => 'false'])
            ->hint('Существующая категория или новый код латиницей.') ?>
        <datalist id="settings-category-options">
            <?php foreach ($categoryOptions as $code => $label): ?>
                <option value="<?= Html::encode($code) ?>"><?= Html::encode($label) ?></option>
            <?php endforeach; ?>
        </datalist>

        <?= $form->field($model, 'code')
            ->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'spellcheck' => 'false', 'autocomplete' => 'off'])
            ->hint('Уникальный код внутри категории. Меняйте его только вместе с кодом приложения.') ?>
    </fieldset>

    <fieldset class="admin-form-section">
        <legend>Значение</legend>
        <?= $form->field($model, 'type')
            ->dropDownList(SettingsCatalog::typeOptions(), ['class' => 'ds-select form-select', 'data-setting-type' => true])
            ->hint('Тип определяет контрол и проверку значения в общем списке.') ?>

        <?= $form->field($model, 'value')
            ->textInput([
                'class' => 'ds-input form-control',
                'type' => $isSecret ? 'password' : 'text',
                'autocomplete' => $isSecret ? 'new-password' : 'off',
                'spellcheck' => 'false',
                'placeholder' => $hasStoredSecret ? 'Оставьте пустым, чтобы сохранить текущий секрет' : '',
                'data-setting-value' => true,
            ])
            ->hint($hasStoredSecret
                ? 'Текущее секретное значение не загружается в HTML. Пустое поле его не изменит.'
                : 'Для токенов и ключей выберите тип «Секретное значение».') ?>

        <?= $form->field($model, 'is_translate')->checkbox([
            'class' => 'form-check-input',
            'labelOptions' => ['class' => 'form-check-label'],
        ])->hint('Включайте только для пользовательского текста, который действительно переводится.') ?>
    </fieldset>

    <footer class="admin-form-card__footer">
        <?= Html::a('Отмена', ['/settings/index', 'category' => $model->category ?: 'site'], ['class' => 'ds-btn ds-btn--ghost']) ?>
        <?= Html::submitButton(
            '<i class="fa-solid fa-check" aria-hidden="true"></i><span>Сохранить параметр</span>',
            ['class' => 'ds-btn ds-btn--primary']
        ) ?>
    </footer>

    <?php ActiveForm::end(); ?>
</div>

<?php
$idType = Html::getInputId($model, 'type');
$idValue = Html::getInputId($model, 'value');
$this->registerJs(<<<JS
(function () {
    var type = document.getElementById('{$idType}');
    var value = document.getElementById('{$idValue}');
    if (!type || !value) return;
    function syncValueType() {
        value.type = type.value === 'password' ? 'password' : (type.value === 'number' ? 'number' : 'text');
        value.autocomplete = type.value === 'password' ? 'new-password' : 'off';
    }
    type.addEventListener('change', syncValueType);
    syncValueType();
})();
JS, View::POS_READY);
?>

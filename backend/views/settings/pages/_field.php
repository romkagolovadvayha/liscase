<?php

use backend\components\SettingsCatalog;
use common\models\site\SiteSetting;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var SiteSetting $item */

$inputId = 'setting-value-' . (int) $item->id;
$helpId = $inputId . '-help';
$sensitive = SettingsCatalog::isSensitive($item);
$type = $sensitive ? 'password' : $item->type;
$systemCode = $item->category . '_' . $item->code;
$searchValue = mb_strtolower($item->name . ' ' . $systemCode);
?>

<article class="settings-field settings-field--<?= Html::encode($type) ?>"
         data-setting-field
         data-search-value="<?= Html::encode($searchValue) ?>">
    <div class="settings-field__meta">
        <div>
            <label for="<?= Html::encode($inputId) ?>"><?= Html::encode($item->name) ?></label>
            <code id="<?= Html::encode($helpId) ?>"><?= Html::encode($systemCode) ?></code>
        </div>
        <a href="<?= Url::to(['/settings/update', 'id' => $item->id]) ?>"
           class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm"
           title="Редактировать структуру параметра"
           aria-label="Редактировать параметр «<?= Html::encode($item->name) ?>»">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
        </a>
    </div>

    <div class="settings-field__control">
        <?php if ($type === 'checkbox'): ?>
            <?= Html::hiddenInput('settings[' . $item->id . ']', '0') ?>
            <label class="admin-switch" for="<?= Html::encode($inputId) ?>">
                <?= Html::checkbox('settings[' . $item->id . ']', (bool) $item->getValue(), [
                    'id' => $inputId,
                    'class' => 'admin-switch__input',
                    'value' => '1',
                    'aria-describedby' => $helpId,
                    'role' => 'switch',
                ]) ?>
                <span class="admin-switch__track" aria-hidden="true"><span></span></span>
                <span class="admin-switch__state" data-switch-state><?= $item->getValue() ? 'Включено' : 'Выключено' ?></span>
            </label>
        <?php elseif ($type === 'color'): ?>
            <div class="admin-color-field">
                <?= Html::input('color', null, $item->value, [
                    'class' => 'admin-color-field__picker color_picker',
                    'id' => $inputId . '-picker',
                    'aria-label' => 'Выбрать цвет для «' . $item->name . '»',
                    'data-color-target' => $inputId,
                ]) ?>
                <?= Html::textInput('settings[' . $item->id . ']', $item->value, [
                    'class' => 'ds-input color_picker_text',
                    'id' => $inputId,
                    'aria-describedby' => $helpId,
                    'spellcheck' => 'false',
                    'pattern' => '#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?',
                    'data-color-picker' => $inputId . '-picker',
                ]) ?>
            </div>
        <?php elseif ($type === 'longtext'): ?>
            <?= Html::textarea('settings[' . $item->id . ']', $item->value, [
                'class' => 'ds-textarea form-control',
                'id' => $inputId,
                'rows' => 6,
                'aria-describedby' => $helpId,
            ]) ?>
        <?php elseif ($type === 'number'): ?>
            <?= Html::input('number', 'settings[' . $item->id . ']', $item->value, [
                'class' => 'ds-input form-control',
                'id' => $inputId,
                'step' => 'any',
                'inputmode' => 'decimal',
                'aria-describedby' => $helpId,
            ]) ?>
        <?php elseif ($type === 'image'): ?>
            <?php if (!empty($item->value)): ?>
                <figure class="settings-media-preview settings-media-preview--image">
                    <img src="<?= Html::encode($item->getValue()) ?>" alt="Текущее изображение: <?= Html::encode($item->name) ?>">
                </figure>
            <?php endif; ?>
            <?= Html::fileInput('settings[' . $item->id . ']', null, [
                'class' => 'ds-input form-control admin-file-input',
                'id' => $inputId,
                'accept' => '.jpg,.jpeg,.png,.svg,.webp,.ico',
                'aria-describedby' => $helpId,
            ]) ?>
            <small>SVG, PNG, JPG, WebP или ICO. Пустое поле сохраняет текущий файл.</small>
        <?php elseif ($type === 'video'): ?>
            <?php if (!empty($item->value)): ?>
                <figure class="settings-media-preview settings-media-preview--video">
                    <video playsinline preload="metadata" loop muted controls>
                        <source type="video/webm" src="<?= Html::encode($item->getValue()) ?>">
                    </video>
                </figure>
            <?php endif; ?>
            <?= Html::fileInput('settings[' . $item->id . ']', null, [
                'class' => 'ds-input form-control admin-file-input',
                'id' => $inputId,
                'accept' => '.webm,video/webm',
                'aria-describedby' => $helpId,
            ]) ?>
            <small>Видео WebM. Пустое поле сохраняет текущий файл.</small>
        <?php elseif ($type === 'password'): ?>
            <div class="admin-secret-field">
                <?= Html::passwordInput('settings[' . $item->id . ']', '', [
                    'class' => 'ds-input form-control',
                    'id' => $inputId,
                    'autocomplete' => 'new-password',
                    'spellcheck' => 'false',
                    'placeholder' => $item->value !== '' ? 'Секрет сохранён — введите новый для замены' : 'Введите секретное значение',
                    'aria-describedby' => $helpId . ' ' . $inputId . '-status',
                ]) ?>
                <button type="button"
                        class="ds-btn ds-btn--icon ds-btn--ghost admin-secret-field__toggle"
                        data-secret-toggle="<?= Html::encode($inputId) ?>"
                        aria-label="Показать введённое значение"
                        aria-pressed="false">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </button>
            </div>
            <small id="<?= Html::encode($inputId) ?>-status" class="settings-secret-status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <?= $item->value !== '' ? 'Значение сохранено и не загружается в страницу.' : 'Значение пока не задано.' ?>
            </small>
        <?php else: ?>
            <?= Html::textInput('settings[' . $item->id . ']', $item->value, [
                'class' => 'ds-input form-control',
                'id' => $inputId,
                'aria-describedby' => $helpId,
            ]) ?>
        <?php endif; ?>
    </div>
</article>

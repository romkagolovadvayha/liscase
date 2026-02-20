<?php

use yii\helpers\Html;

$this->title = 'Формы - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Формы';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Input -->
        <section class="mb-5">
            <h2 class="mb-4">Поля ввода</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-label">Обычное поле</label>
                    <input type="text" class="ds-input" placeholder="Введите текст">
                </div>
                <div class="ds-form-group">
                    <label class="ds-label ds-label--required">Обязательное поле</label>
                    <input type="text" class="ds-input" placeholder="Обязательное поле">
                </div>
                <div class="ds-form-group">
                    <label class="ds-label">Поле с ошибкой</label>
                    <input type="text" class="ds-input ds-input--error" value="Некорректное значение">
                    <div class="ds-form-group__error">Это поле содержит ошибку</div>
                </div>
                <div class="ds-form-group">
                    <label class="ds-label">Поле с подсказкой</label>
                    <input type="text" class="ds-input" placeholder="Введите email">
                    <div class="ds-form-group__help">Введите корректный email адрес</div>
                </div>
                <div class="ds-form-group">
                    <label class="ds-label">Отключенное поле</label>
                    <input type="text" class="ds-input" value="Недоступно" disabled>
                </div>
            </div>
        </section>

        <!-- Textarea -->
        <section class="mb-5">
            <h2 class="mb-4">Textarea</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-label">Многострочное поле</label>
                    <textarea class="ds-textarea" rows="4" placeholder="Введите текст"></textarea>
                </div>
            </div>
        </section>

        <!-- Select -->
        <section class="mb-5">
            <h2 class="mb-4">Select</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-label">Выпадающий список</label>
                    <select class="ds-select">
                        <option>Вариант 1</option>
                        <option>Вариант 2</option>
                        <option>Вариант 3</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Checkbox и Radio -->
        <section class="mb-5">
            <h2 class="mb-4">Checkbox и Radio</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-checkbox-label">
                        <input type="checkbox" class="ds-checkbox"> Чекбокс
                    </label>
                </div>
                <div class="ds-form-group">
                    <label class="ds-radio-label">
                        <input type="radio" name="radio" class="ds-radio" checked> Радио 1
                    </label>
                    <label class="ds-radio-label">
                        <input type="radio" name="radio" class="ds-radio"> Радио 2
                    </label>
                </div>
            </div>
        </section>

        <!-- Switch -->
        <section class="mb-5">
            <h2 class="mb-4">Switch (Toggle)</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-label">Переключатель</label>
                    <div class="ds-switch">
                        <input type="checkbox" id="switch1">
                        <span class="ds-switch__slider"></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Размеры -->
        <section class="mb-5">
            <h2 class="mb-4">Размеры полей</h2>
            <div class="ds-card">
                <div class="ds-form-group">
                    <label class="ds-label">Small</label>
                    <input type="text" class="ds-input ds-input--sm" placeholder="Small input">
                </div>
                <div class="ds-form-group">
                    <label class="ds-label">Normal</label>
                    <input type="text" class="ds-input" placeholder="Normal input">
                </div>
                <div class="ds-form-group">
                    <label class="ds-label">Large</label>
                    <input type="text" class="ds-input ds-input--lg" placeholder="Large input">
                </div>
            </div>
        </section>
    </div>
</div>

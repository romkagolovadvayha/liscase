<?php

use yii\helpers\Html;

$this->title = 'Мобильная версия - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Мобильная версия';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Breakpoints -->
        <section class="mb-5">
            <h2 class="mb-4">Breakpoints</h2>
            <div class="ds-card">
                <ul>
                    <li><strong>Mobile:</strong> &lt; 640px</li>
                    <li><strong>Tablet:</strong> 640px - 1024px</li>
                    <li><strong>Desktop:</strong> &gt; 1024px</li>
                </ul>
            </div>
        </section>

        <!-- Мобильный Sidebar -->
        <section class="mb-5">
            <h2 class="mb-4">Мобильный Sidebar</h2>
            <div class="ds-card">
                <p>На мобильных устройствах sidebar превращается в off-canvas меню, которое открывается по клику на кнопку гамбургер-меню.</p>
                <p class="ds-text--secondary">Используйте классы <code>ds-hidden-mobile</code> и <code>ds-visible-mobile</code> для управления видимостью элементов.</p>
            </div>
        </section>

        <!-- Адаптивные утилиты -->
        <section class="mb-5">
            <h2 class="mb-4">Адаптивные утилиты</h2>
            <div class="ds-card">
                <div class="ds-card__header">
                    <h5 class="ds-card__header-title">Классы для управления видимостью</h5>
                </div>
                <div class="ds-card__body">
                    <ul>
                        <li><code>ds-hidden-mobile</code> - Скрыть на мобильных</li>
                        <li><code>ds-hidden-desktop</code> - Скрыть на десктопе</li>
                        <li><code>ds-visible-mobile</code> - Показать только на мобильных</li>
                        <li><code>ds-visible-desktop</code> - Показать только на десктопе</li>
                        <li><code>ds-flex-mobile-col</code> - Flex column на мобильных</li>
                        <li><code>ds-btn--block-mobile</code> - Block кнопка на мобильных</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</div>

<?php

use yii\helpers\Html;

$this->title = 'Типографика - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Типографика';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Заголовки -->
        <section class="mb-5">
            <h2 class="mb-4">Заголовки</h2>
            <div class="ds-card">
                <h1>Заголовок H1</h1>
                <h2>Заголовок H2</h2>
                <h3>Заголовок H3</h3>
                <h4>Заголовок H4</h4>
                <h5>Заголовок H5</h5>
                <h6>Заголовок H6</h6>
            </div>
        </section>

        <!-- Размеры текста -->
        <section class="mb-5">
            <h2 class="mb-4">Размеры текста</h2>
            <div class="ds-card">
                <p class="ds-text-xs">Extra Small (0.75rem / 12px) - ds-text-xs</p>
                <p class="ds-text-sm">Small (0.875rem / 14px) - ds-text-sm</p>
                <p class="ds-text-base">Base (1rem / 16px) - ds-text-base</p>
                <p class="ds-text-lg">Large (1.125rem / 18px) - ds-text-lg</p>
                <p class="ds-text-xl">Extra Large (1.25rem / 20px) - ds-text-xl</p>
                <p class="ds-text-2xl">2X Large (1.5rem / 24px) - ds-text-2xl</p>
                <p class="ds-text-3xl">3X Large (1.875rem / 30px) - ds-text-3xl</p>
            </div>
        </section>

        <!-- Веса шрифтов -->
        <section class="mb-5">
            <h2 class="mb-4">Веса шрифтов</h2>
            <div class="ds-card">
                <p class="ds-font-thin">Thin (100) - ds-font-thin</p>
                <p class="ds-font-light">Light (300) - ds-font-light</p>
                <p class="ds-font-normal">Normal (400) - ds-font-normal</p>
                <p class="ds-font-medium">Medium (500) - ds-font-medium</p>
                <p class="ds-font-semibold">Semibold (600) - ds-font-semibold</p>
                <p class="ds-font-bold">Bold (700) - ds-font-bold</p>
            </div>
        </section>

        <!-- Цвета текста -->
        <section class="mb-5">
            <h2 class="mb-4">Цвета текста</h2>
            <div class="ds-card">
                <p class="ds-text--primary">Primary Text - ds-text--primary</p>
                <p class="ds-text--secondary">Secondary Text - ds-text--secondary</p>
                <p class="ds-text--muted">Muted Text - ds-text--muted</p>
                <p class="ds-text--success">Success Text - ds-text--success</p>
                <p class="ds-text--danger">Danger Text - ds-text--danger</p>
                <p class="ds-text--warning">Warning Text - ds-text--warning</p>
                <p class="ds-text--info">Info Text - ds-text--info</p>
            </div>
        </section>

        <!-- Выравнивание -->
        <section class="mb-5">
            <h2 class="mb-4">Выравнивание текста</h2>
            <div class="ds-card">
                <p class="ds-text-left">Left aligned text - ds-text-left</p>
                <p class="ds-text-center">Center aligned text - ds-text-center</p>
                <p class="ds-text-right">Right aligned text - ds-text-right</p>
            </div>
        </section>

        <!-- Трансформация -->
        <section class="mb-5">
            <h2 class="mb-4">Трансформация текста</h2>
            <div class="ds-card">
                <p class="ds-uppercase">uppercase text - ds-uppercase</p>
                <p class="ds-lowercase">LOWERCASE TEXT - ds-lowercase</p>
                <p class="ds-capitalize">capitalize text - ds-capitalize</p>
            </div>
        </section>
    </div>
</div>

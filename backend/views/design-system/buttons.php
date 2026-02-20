<?php

use yii\helpers\Html;

$this->title = 'Кнопки - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Кнопки';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Варианты цветов -->
        <section class="mb-5">
            <h2 class="mb-4">Варианты цветов</h2>
            <div class="ds-card">
                <div class="ds-flex ds-flex-wrap ds-gap-3 mb-3">
                    <button class="ds-btn ds-btn--primary">Primary</button>
                    <button class="ds-btn ds-btn--secondary">Secondary</button>
                    <button class="ds-btn ds-btn--success">Success</button>
                    <button class="ds-btn ds-btn--danger">Danger</button>
                    <button class="ds-btn ds-btn--warning">Warning</button>
                    <button class="ds-btn ds-btn--info">Info</button>
                    <button class="ds-btn ds-btn--ghost">Ghost</button>
                    <button class="ds-btn ds-btn--link">Link</button>
                </div>
            </div>
        </section>

        <!-- Размеры -->
        <section class="mb-5">
            <h2 class="mb-4">Размеры</h2>
            <div class="ds-card">
                <div class="ds-flex ds-items-center ds-flex-wrap ds-gap-3 mb-3">
                    <button class="ds-btn ds-btn--primary ds-btn--xs">Extra Small</button>
                    <button class="ds-btn ds-btn--primary ds-btn--sm">Small</button>
                    <button class="ds-btn ds-btn--primary ds-btn--md">Medium</button>
                    <button class="ds-btn ds-btn--primary ds-btn--lg">Large</button>
                    <button class="ds-btn ds-btn--primary ds-btn--xl">Extra Large</button>
                </div>
            </div>
        </section>

        <!-- С иконками -->
        <section class="mb-5">
            <h2 class="mb-4">С иконками</h2>
            <div class="ds-card">
                <div class="ds-flex ds-flex-wrap ds-gap-3 mb-3">
                    <button class="ds-btn ds-btn--primary">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                    <button class="ds-btn ds-btn--success">
                        <i class="fas fa-check"></i> Применить
                    </button>
                    <button class="ds-btn ds-btn--danger">
                        <i class="fas fa-trash"></i> Удалить
                    </button>
                    <button class="ds-btn ds-btn--icon ds-btn--primary">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- Состояния -->
        <section class="mb-5">
            <h2 class="mb-4">Состояния</h2>
            <div class="ds-card">
                <div class="ds-flex ds-flex-wrap ds-gap-3 mb-3">
                    <button class="ds-btn ds-btn--primary">Normal</button>
                    <button class="ds-btn ds-btn--primary" disabled>Disabled</button>
                    <button class="ds-btn ds-btn--primary ds-btn--loading">Loading</button>
                </div>
            </div>
        </section>

        <!-- Block кнопка -->
        <section class="mb-5">
            <h2 class="mb-4">Block кнопка</h2>
            <div class="ds-card">
                <button class="ds-btn ds-btn--primary ds-btn--block">Block Button</button>
            </div>
        </section>

        <!-- Примеры использования -->
        <section class="mb-5">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h5 class="ds-card__header-title">Примеры использования</h5>
                </div>
                <div class="ds-card__body">
                    <pre class="ds-bg--secondary ds-p-4 ds-rounded-lg" style="overflow-x: auto;"><code>&lt;!-- Основная кнопка --&gt;
&lt;button class="ds-btn ds-btn--primary"&gt;Кнопка&lt;/button&gt;

&lt;!-- Кнопка с иконкой --&gt;
&lt;button class="ds-btn ds-btn--success"&gt;
    &lt;i class="fas fa-check"&gt;&lt;/i&gt; Сохранить
&lt;/button&gt;

&lt;!-- Маленькая кнопка --&gt;
&lt;button class="ds-btn ds-btn--primary ds-btn--sm"&gt;Small&lt;/button&gt;

&lt;!-- Отключенная кнопка --&gt;
&lt;button class="ds-btn ds-btn--primary" disabled&gt;Disabled&lt;/button&gt;

&lt;!-- Block кнопка --&gt;
&lt;button class="ds-btn ds-btn--primary ds-btn--block"&gt;Block&lt;/button&gt;</code></pre>
                </div>
            </div>
        </section>
    </div>
</div>

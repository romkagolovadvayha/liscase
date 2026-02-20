<?php

use yii\helpers\Html;

$this->title = 'Цветовая палитра - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Цветовая палитра';

$colors = [
    'primary' => ['#00536c', 'Primary', 'Основной акцентный цвет'],
    'success' => ['#006c2e', 'Success', 'Цвет успешных операций'],
    'danger' => ['#6c0000', 'Danger', 'Цвет ошибок и предупреждений'],
    'warning' => ['#856404', 'Warning', 'Цвет предупреждений'],
    'info' => ['#0c5460', 'Info', 'Информационный цвет'],
];

$backgrounds = [
    'primary' => ['hsl(0 0% 7.8% / 1)', 'Primary Background', 'Основной фон страницы'],
    'secondary' => ['hsl(0 0% 11.8% / 1)', 'Secondary Background', 'Вторичный фон (карточки, инпуты)'],
    'tertiary' => ['hsl(0 0% 15.3% / 1)', 'Tertiary Background', 'Третичный фон (модалки, таблицы)'],
    'hover' => ['hsl(0 0% 20.4% / 1)', 'Hover Background', 'Фон при наведении'],
];

$texts = [
    'primary' => ['hsl(0 0% 94.9% / 1)', 'Primary Text', 'Основной текст'],
    'secondary' => ['hsl(0 0% 55.3% / 1)', 'Secondary Text', 'Вторичный текст'],
    'muted' => ['hsl(0 0% 40% / 1)', 'Muted Text', 'Приглушенный текст'],
];

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Акцентные цвета -->
        <section class="mb-5">
            <h2 class="mb-4">Акцентные цвета</h2>
            <div class="row">
                <?php foreach ($colors as $key => $color): ?>
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="ds-card">
                            <div style="background: <?= $color[0] ?>; height: 120px; border-radius: 8px; margin-bottom: 1rem;"></div>
                            <strong class="ds-text--primary"><?= $color[1] ?></strong>
                            <div class="ds-text--muted small mb-2"><?= $color[2] ?></div>
                            <div class="ds-text--secondary small">
                                <code><?= $color[0] ?></code>
                            </div>
                            <div class="ds-text--secondary small">
                                <code>$ds-<?= $key ?></code>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Фоны -->
        <section class="mb-5">
            <h2 class="mb-4">Фоны</h2>
            <div class="row">
                <?php foreach ($backgrounds as $key => $bg): ?>
                    <div class="col-md-3 mb-3">
                        <div class="ds-card">
                            <div style="background: <?= $bg[0] ?>; height: 100px; border-radius: 8px; margin-bottom: 1rem; border: 1px solid hsl(0 0% 15.3% / 1);"></div>
                            <strong class="ds-text--primary"><?= $bg[1] ?></strong>
                            <div class="ds-text--muted small mb-2"><?= $bg[2] ?></div>
                            <div class="ds-text--secondary small">
                                <code>$ds-bg-<?= $key ?></code>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Текст -->
        <section class="mb-5">
            <h2 class="mb-4">Цвета текста</h2>
            <div class="row">
                <?php foreach ($texts as $key => $text): ?>
                    <div class="col-md-4 mb-3">
                        <div class="ds-card">
                            <div style="background: <?= $text[0] ?>; height: 60px; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: <?= $key === 'primary' ? 'hsl(0 0% 7.8% / 1)' : 'hsl(0 0% 94.9% / 1)' ?>; font-weight: 600;">
                                Sample Text
                            </div>
                            <strong class="ds-text--primary"><?= $text[1] ?></strong>
                            <div class="ds-text--muted small mb-2"><?= $text[2] ?></div>
                            <div class="ds-text--secondary small">
                                <code>$ds-text-<?= $key ?></code>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Использование -->
        <section class="mb-5">
            <div class="ds-card">
                <div class="ds-card__header">
                    <h5 class="ds-card__header-title">Использование в CSS/SCSS</h5>
                </div>
                <div class="ds-card__body">
                    <pre class="ds-bg--secondary ds-p-4 ds-rounded-lg" style="overflow-x: auto;"><code>// В SCSS
.my-element {
  background: $ds-bg-primary;
  color: $ds-text-primary;
  border: 1px solid $ds-primary;
}

// В CSS (после компиляции)
.my-element {
  background: hsl(0 0% 7.8% / 1);
  color: hsl(0 0% 94.9% / 1);
  border: 1px solid #00536c;
}</code></pre>
                </div>
            </div>
        </section>
    </div>
</div>

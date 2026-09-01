<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Дизайн-система';
$this->params['breadcrumbs'][] = $this->title;

$sections = [
    [
        'title' => 'Цветовая палитра',
        'description' => 'Цвета, используемые в дизайн-системе',
        'url' => ['colors'],
        'icon' => 'fas fa-palette',
        'color' => 'primary'
    ],
    [
        'title' => 'Типографика',
        'description' => 'Шрифты, размеры, веса и высоты строк',
        'url' => ['typography'],
        'icon' => 'fas fa-font',
        'color' => 'primary'
    ],
    [
        'title' => 'Кнопки',
        'description' => 'Все варианты кнопок и их состояния',
        'url' => ['buttons'],
        'icon' => 'fas fa-mouse-pointer',
        'color' => 'success'
    ],
    [
        'title' => 'Формы',
        'description' => 'Поля ввода, селекты, чекбоксы и другие элементы форм',
        'url' => ['forms'],
        'icon' => 'fas fa-edit',
        'color' => 'info'
    ],
    [
        'title' => 'Карточки',
        'description' => 'Карточки для отображения контента',
        'url' => ['cards'],
        'icon' => 'fas fa-id-card',
        'color' => 'primary'
    ],
    [
        'title' => 'Таблицы',
        'description' => 'Таблицы и GridView компоненты',
        'url' => ['tables'],
        'icon' => 'fas fa-table',
        'color' => 'warning'
    ],
    [
        'title' => 'Модальные окна',
        'description' => 'Всплывающие окна и диалоги',
        'url' => ['modals'],
        'icon' => 'fas fa-window-maximize',
        'color' => 'info'
    ],
    [
        'title' => 'Навигация',
        'description' => 'Sidebar, Navbar, Breadcrumbs, Tabs, Pagination',
        'url' => ['navigation'],
        'icon' => 'fas fa-compass',
        'color' => 'primary'
    ],
    [
        'title' => 'Обратная связь',
        'description' => 'Alerts, Toasts, Badges, Progress bars, Loading spinners',
        'url' => ['feedback'],
        'icon' => 'fas fa-bell',
        'color' => 'success'
    ],
    [
        'title' => 'Макет',
        'description' => 'Структура страниц и layout компоненты',
        'url' => ['layout'],
        'icon' => 'fas fa-th',
        'color' => 'warning'
    ],
    [
        'title' => 'Мобильная версия',
        'description' => 'Адаптивность и мобильные компоненты',
        'url' => ['mobile'],
        'icon' => 'fas fa-mobile-alt',
        'color' => 'info'
    ],
];

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
        <p class="ds-text--secondary">Полная документация всех компонентов дизайн-системы админ-панели</p>
    </div>

    <div class="content">
        <div class="row">
            <?php foreach ($sections as $section): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ds-card ds-card--hover">
                        <div class="ds-card__body">
                            <div class="ds-flex ds-items-center ds-gap-4 mb-3">
                                <div class="ds-bg--<?= $section['color'] ?> ds-rounded-lg design-system-section-icon">
                                    <i class="<?= $section['icon'] ?>" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <h3 class="ds-text--primary ds-m-0 ds-text-xl ds-font-semibold">
                                        <?= Html::encode($section['title']) ?>
                                    </h3>
                                </div>
                            </div>
                            <p class="ds-text--secondary ds-mb-4">
                                <?= Html::encode($section['description']) ?>
                            </p>
                            <?= Html::a('Перейти →', $section['url'], ['class' => 'ds-btn ds-btn--primary']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ds-card mt-4">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Быстрый старт</h5>
            </div>
            <div class="ds-card__body">
                <h6 class="ds-text--primary mb-3">Использование компонентов</h6>
                <p class="ds-text--secondary mb-3">
                    Все компоненты дизайн-системы используют префикс <code class="ds-bg--secondary ds-p-1 ds-rounded-sm">ds-</code>.
                    Например: <code class="ds-bg--secondary ds-p-1 ds-rounded-sm">ds-btn</code>, <code class="ds-bg--secondary ds-p-1 ds-rounded-sm">ds-card</code>, <code class="ds-bg--secondary ds-p-1 ds-rounded-sm">ds-input</code>.
                </p>
                
                <h6 class="ds-text--primary mb-3 mt-4">Примеры использования</h6>
                <pre class="ds-bg--secondary ds-p-4 ds-rounded-lg ds-overflow-x-auto"><code>&lt;button class="ds-btn ds-btn--primary"&gt;Кнопка&lt;/button&gt;

&lt;div class="ds-card"&gt;
    &lt;div class="ds-card__header"&gt;
        &lt;h5 class="ds-card__header-title"&gt;Заголовок&lt;/h5&gt;
    &lt;/div&gt;
    &lt;div class="ds-card__body"&gt;
        Содержимое карточки
    &lt;/div&gt;
&lt;/div&gt;

&lt;input type="text" class="ds-input" placeholder="Введите текст"&gt;</code></pre>
            </div>
        </div>
    </div>
</div>

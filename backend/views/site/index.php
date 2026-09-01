<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */

$this->title = 'Обзор';
$this->params['breadcrumbs'][] = $this->title;

$quickLinks = [
    ['label' => 'Игроки', 'description' => 'Профили, статусы и балансы', 'icon' => 'fa-users', 'route' => ['/user/index']],
    ['label' => 'Поддержка', 'description' => 'Обращения и ответы игрокам', 'icon' => 'fa-headset', 'route' => ['/support/index']],
    ['label' => 'Серверы', 'description' => 'Состояние, вайпы и параметры', 'icon' => 'fa-server', 'route' => ['/servers/index']],
    ['label' => 'Каталог', 'description' => 'Категории, предметы и наборы', 'icon' => 'fa-box-open', 'route' => ['/category/index']],
    ['label' => 'Отчёты', 'description' => 'Продажи, пополнения и сводки', 'icon' => 'fa-chart-line', 'route' => ['/reports/index']],
    ['label' => 'Рассылки', 'description' => 'Telegram и шаблоны сообщений', 'icon' => 'fa-paper-plane', 'route' => ['/telegram-constructor/index']],
    ['label' => 'Настройки', 'description' => 'Все параметры проекта по разделам', 'icon' => 'fa-sliders-h', 'route' => ['/settings/index']],
    ['label' => 'Дизайн-система', 'description' => 'Компоненты и состояния интерфейса', 'icon' => 'fa-layer-group', 'route' => ['/design-system/index']],
];
?>

<div class="admin-dashboard">
    <header class="admin-dashboard__intro">
        <div>
            <span class="admin-dashboard__eyebrow">Панель управления</span>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>Быстрый доступ к ежедневным задачам. Полное меню со всеми инструментами находится слева.</p>
        </div>
        <?= Html::a(
            '<i class="fas fa-external-link-alt" aria-hidden="true"></i><span>Открыть сайт</span>',
            Yii::$app->params['baseUrl'],
            ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener']
        ) ?>
    </header>

    <nav class="admin-dashboard__grid" aria-label="Быстрый доступ">
        <?php foreach ($quickLinks as $link): ?>
            <a class="admin-dashboard-card" href="<?= Html::encode(Url::to($link['route'])) ?>">
                <span class="admin-dashboard-card__icon" aria-hidden="true">
                    <i class="fas <?= Html::encode($link['icon']) ?>"></i>
                </span>
                <span class="admin-dashboard-card__content">
                    <strong><?= Html::encode($link['label']) ?></strong>
                    <span><?= Html::encode($link['description']) ?></span>
                </span>
                <i class="fas fa-chevron-right admin-dashboard-card__arrow" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

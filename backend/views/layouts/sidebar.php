<?php

use backend\components\AdminNavigation;
use yii\helpers\Html;
use yii\helpers\Url;

$sections = AdminNavigation::sections();
$menuIndex = 0;

$renderItem = static function (array $item, int $level = 0) use (&$renderItem, &$menuIndex): string {
    $menuIndex++;
    $hasChildren = !empty($item['items']);
    $active = !empty($item['active']);
    $label = (string) ($item['label'] ?? '');
    $icon = (string) ($item['icon'] ?? 'fa-regular fa-circle');
    $badge = isset($item['badge']) && (int) $item['badge'] > 0 ? (int) $item['badge'] : null;
    $submenuId = 'admin-submenu-' . $menuIndex;
    $levelClass = $level > 0 ? ' sidebar-menu-item--nested' : '';

    ob_start();
    ?>
    <li class="sidebar-menu-item<?= $levelClass ?><?= $active ? ' is-active' : '' ?>" data-sidebar-menu-item>
        <?php if ($hasChildren): ?>
            <button type="button"
                    class="sidebar-menu-link sidebar-submenu-toggle<?= $active ? ' is-active' : '' ?>"
                    aria-expanded="<?= $active ? 'true' : 'false' ?>"
                    aria-controls="<?= $submenuId ?>"
                    data-submenu-target="<?= $submenuId ?>"
                    title="<?= Html::encode($label) ?>">
                <i class="<?= Html::encode($icon) ?> sidebar-menu-icon" aria-hidden="true"></i>
                <span class="sidebar-menu-text"><?= Html::encode($label) ?></span>
                <?php if ($badge !== null): ?><span class="sidebar-menu-badge" aria-label="<?= $badge ?> новых"><?= $badge ?></span><?php endif; ?>
                <i class="fa-solid fa-chevron-down sidebar-menu-arrow" aria-hidden="true"></i>
            </button>
            <ul class="sidebar-submenu" id="<?= $submenuId ?>"<?= $active ? '' : ' hidden' ?>>
                <?php foreach ($item['items'] as $child): ?>
                    <?= $renderItem($child, $level + 1) ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <a href="<?= Url::to($item['url']) ?>"
               class="sidebar-menu-link<?= $active ? ' is-active' : '' ?>"
               <?= $active ? 'aria-current="page"' : '' ?>
               title="<?= Html::encode($label) ?>">
                <i class="<?= Html::encode($icon) ?> sidebar-menu-icon" aria-hidden="true"></i>
                <span class="sidebar-menu-text"><?= Html::encode($label) ?></span>
                <?php if ($badge !== null): ?><span class="sidebar-menu-badge" aria-label="<?= $badge ?> новых"><?= $badge ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
    </li>
    <?php
    return (string) ob_get_clean();
};

$siteTitle = (string) (Yii::$app->settings->get('site_title') ?: 'Prostoj');
$logo = (string) Yii::$app->settings->get('design_logo');
?>

<aside class="admin-sidebar-content" id="main-sidebar" aria-label="Основная навигация">
    <div class="sidebar-brand-row">
        <a href="<?= Url::to(['/site/index']) ?>" class="sidebar-brand" aria-label="<?= Html::encode($siteTitle) ?> — обзор">
            <?php if ($logo !== ''): ?>
                <img src="<?= Html::encode($logo) ?>" alt="" class="sidebar-logo">
            <?php else: ?>
                <span class="sidebar-brand-mark" aria-hidden="true">P</span>
            <?php endif; ?>
            <span class="sidebar-brand-copy">
                <strong><?= Html::encode($siteTitle) ?></strong>
                <small>Управление проектом</small>
            </span>
        </a>
        <button type="button"
                class="ds-btn ds-btn--icon ds-btn--ghost sidebar-toggle-btn"
                id="sidebar-collapse-btn"
                aria-label="Свернуть меню"
                aria-controls="main-sidebar"
                aria-expanded="true"
                title="Свернуть меню">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>

    <div class="sidebar-search-wrap">
        <label for="sidebar-menu-search" class="sidebar-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span class="visually-hidden">Найти раздел меню</span>
            <input id="sidebar-menu-search" type="search" placeholder="Найти раздел" autocomplete="off" data-sidebar-search>
            <kbd>/</kbd>
        </label>
    </div>

    <nav class="sidebar-nav" aria-label="Разделы админки">
        <?php foreach ($sections as $section): ?>
            <section class="sidebar-section" data-sidebar-section>
                <h2 class="sidebar-section-title"><?= Html::encode($section['label']) ?></h2>
                <ul class="sidebar-menu">
                    <?php foreach ($section['items'] as $item): ?>
                        <?= $renderItem($item) ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
        <p class="sidebar-search-empty" data-sidebar-empty hidden>Ничего не найдено</p>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= Url::to(['/settings/index']) ?>" class="sidebar-footer-link" title="Настройки">
            <i class="fa-solid fa-gear" aria-hidden="true"></i><span>Настройки</span>
        </a>
        <a href="<?= Html::encode(Yii::$app->params['baseUrl'] ?? '/') ?>" class="sidebar-footer-link" target="_blank" rel="noopener" title="Открыть сайт">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Открыть сайт</span>
        </a>
        <?= Html::a(
            '<i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Выйти</span>',
            ['/site/logout'],
            ['data-method' => 'post', 'class' => 'sidebar-footer-link', 'title' => 'Выйти из админки']
        ) ?>
    </div>
</aside>

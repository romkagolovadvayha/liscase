<?php

use yii\helpers\Html;

/** @var string $pageTitle */
/** @var array|null $headerActions */
/** @var bool $showFilters */

$pageTitle = $pageTitle ?? '';
$headerActions = $headerActions ?? $this->params['headerActions'] ?? [];
$showFilters = $showFilters ?? false;
$identity = Yii::$app->user->identity;
$username = (string) ($identity->username ?? 'Администратор');
$avatarUrl = $identity && method_exists($identity, 'getAvatar') ? (string) $identity->getAvatar() : '';
?>

<header class="admin-header-content">
    <div class="admin-header-bar">
        <div class="admin-header-leading">
            <button type="button"
                    class="ds-btn ds-btn--icon ds-btn--ghost admin-mobile-menu-button"
                    aria-label="Открыть основное меню"
                    aria-controls="main-sidebar"
                    aria-expanded="false"
                    id="mobile-menu-toggle">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>

            <?php if ($pageTitle !== ''): ?>
                <div class="page-title-header">
                    <span class="page-title-header__context">Админка</span>
                    <div class="page-title-header__title"><?= Html::encode($pageTitle) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($headerActions !== []): ?>
            <nav class="admin-header-actions" aria-label="Действия страницы">
                <?php foreach ($headerActions as $action): ?>
                    <?php
                    $legacyClass = (string) ($action['class'] ?? '');
                    $plainLabel = trim(strip_tags((string) ($action['label'] ?? 'Действие')));
                    $variant = (str_contains($legacyClass, 'ds-btn--danger') || str_contains($legacyClass, 'red-'))
                        ? 'ds-btn--danger'
                        : ((str_contains($legacyClass, 'ds-btn--primary') || str_contains($legacyClass, 'blue-') || str_contains($legacyClass, 'green-') || str_contains($legacyClass, 'teal-'))
                            ? 'ds-btn--primary'
                            : 'ds-btn--secondary');
                    $options = array_merge(
                        ['class' => 'ds-btn ' . $variant . ' ds-btn--sm', 'aria-label' => $plainLabel],
                        array_diff_key($action, ['label' => true, 'url' => true, 'class' => true, 'encode' => true])
                    );
                    ?>
                    <?= Html::a($action['label'], $action['url'], $options) ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="admin-header-trailing">
            <?php if ($showFilters): ?>
                <button type="button"
                        class="ds-btn ds-btn--secondary ds-btn--sm"
                        id="filters-drawer-toggle"
                        aria-label="Открыть фильтры"
                        aria-controls="filters-wrapper"
                        aria-expanded="false">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    <span class="filters-btn-text">Фильтры</span>
                </button>
            <?php endif; ?>

            <div class="admin-user-menu">
                <button type="button"
                        class="admin-user-menu__toggle"
                        id="user-menu-toggle"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-controls="admin-user-dropdown">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?= Html::encode($avatarUrl) ?>" alt="" width="32" height="32">
                    <?php else: ?>
                        <span class="admin-user-menu__avatar" aria-hidden="true"><?= Html::encode(mb_strtoupper(mb_substr($username, 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="admin-user-menu__name"><?= Html::encode($username) ?></span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>

                <div class="admin-user-dropdown" id="admin-user-dropdown" role="menu" hidden>
                    <div class="admin-user-dropdown__identity">
                        <strong><?= Html::encode($username) ?></strong>
                        <small>Панель управления</small>
                    </div>
                    <?= Html::a('<i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Настройки</span>', ['/settings/index'], ['role' => 'menuitem']) ?>
                    <?= Html::a('<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Открыть сайт</span>', Yii::$app->params['baseUrl'] ?? '/', ['role' => 'menuitem', 'target' => '_blank', 'rel' => 'noopener']) ?>
                    <div class="admin-user-dropdown__separator" role="separator"></div>
                    <?= Html::a(
                        '<i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Выйти</span>',
                        ['/site/logout'],
                        ['data-method' => 'post', 'role' => 'menuitem', 'class' => 'is-danger']
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</header>

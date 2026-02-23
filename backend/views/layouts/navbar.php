<?php
/* @var $pageTitle string */
/* @var $headerActions array|null */
/* @var $showFilters bool */

use yii\helpers\Html;
use yii\helpers\Url;

$pageTitle = $pageTitle ?? '';
$headerActions = $headerActions ?? $this->params['headerActions'] ?? null;
$showFilters = $showFilters ?? false;
?>
<!-- Modern Header -->
<header class="admin-header-content bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]">
    <div class="flex items-center justify-between px-4 py-3" style="height: 57px;">
        <!-- Left: Logo and Menu Toggle (только на мобилке) -->
        <div class="flex items-center gap-4 md:hidden">
            <button 
                class="ds-btn ds-btn--icon ds-btn--ghost" 
                data-widget="pushmenu" 
                href="#" 
                role="button" 
                aria-label="Toggle sidebar"
                id="mobile-menu-toggle"
            >
                <i class="fas fa-bars"></i>
            </button>
            
            <a href="<?= Yii::$app->params['baseUrl'] ?? '/' ?>" class="flex items-center gap-2 text-white no-underline font-semibold">
                <?php if (Yii::$app->settings->get('design_logo')): ?>
                    <img src="<?= Yii::$app->settings->get('design_logo') ?>" alt="<?= Yii::$app->settings->get('site_title') ?>" class="h-8 max-w-[120px]">
                <?php else: ?>
                    <span class="text-xl"><?= Html::encode(Yii::$app->settings->get('site_title') ?: 'Prostoj') ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Left: Page Title and Header Actions -->
        <div class="flex items-center gap-4 flex-1">
            <!-- Page Title (только на ПК) -->
            <?php if (!empty($pageTitle)): ?>
            <style>
            @media (min-width: 768px) {
                .page-title-header {
                    display: flex !important;
                    align-items: center;
                }
            }
            @media (max-width: 767px) {
                .page-title-header {
                    display: none !important;
                }
            }
            </style>
            <div class="page-title-header">
                <h1 class="text-lg font-semibold text-white m-0"><?= Html::encode($pageTitle) ?></h1>
            </div>
            <?php endif; ?>
            
            <!-- Header Actions (кнопки из params) -->
            <?php if (!empty($headerActions)): ?>
            <div class="flex items-center gap-2">
                <?php foreach ($headerActions as $action): ?>
                    <?php
                    $buttonClass = $action['class'] ?? 'bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5';
                    $buttonClass = str_replace(['px-3 py-1.5', 'text-sm'], ['px-2 py-1', 'text-xs'], $buttonClass);
                    $options = array_merge(['class' => $buttonClass], array_diff_key($action, ['label' => 1, 'url' => 1, 'class' => 1]));
                    ?>
                    <?= Html::a($action['label'], $action['url'], $options) ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-3">
            <!-- Кнопка «Фильтры» на мобилке (открывает выдвижную панель) -->
            <?php if ($showFilters): ?>
            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm inline-flex items-center gap-1.5" id="filters-drawer-toggle" aria-label="Открыть фильтры">
                <i class="fas fa-filter" aria-hidden="true"></i>
                <span class="filters-btn-text">Фильтры</span>
            </button>
            <?php endif; ?>

            <!-- User Info -->
            <div class="flex items-center gap-2">
                <span class="text-gray-400 text-sm hidden md:inline">
                    <?= Html::encode(Yii::$app->user->identity->username ?? 'User') ?>
                </span>
                <div class="relative">
                    <button class="ds-btn ds-btn--icon ds-btn--ghost p-0 rounded-full overflow-hidden" id="user-menu-toggle" aria-label="User menu">
                        <?php
                        $identity = Yii::$app->user->identity;
                        $avatarUrl = $identity && method_exists($identity, 'getAvatar') ? $identity->getAvatar() : '';
                        if (!empty($avatarUrl)): ?>
                            <img src="<?= Html::encode($avatarUrl) ?>" alt="" class="w-8 h-8 rounded-full object-cover" width="32" height="32" />
                        <?php else: ?>
                            <i class="fas fa-user-circle text-2xl text-gray-400"></i>
                        <?php endif; ?>
                    </button>
                    <!-- User Dropdown (can be added later) -->
                </div>
            </div>

            <!-- Logout -->
            <?= Html::a(
                '<i class="fas fa-sign-out-alt"></i>',
                ['/site/logout'],
                [
                    'data-method' => 'post',
                    'class' => 'ds-btn ds-btn--icon ds-btn--ghost',
                    'aria-label' => 'Выход',
                    'title' => 'Выход'
                ]
            ) ?>
        </div>
    </div>

</header>

<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use common\components\widgets\ModalWidget;

\backend\assets\FontAwesomeAsset::register($this);
\hail812\adminlte3\assets\AdminLteAsset::register($this);
\backend\assets\AppAsset::register($this);
\backend\assets\BootstrapAsset::register($this);
\common\assets\BootstrapIcons::register($this);
\frontend\assets\ModalAsset::register($this);
$this->registerCssFile('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback');

$assetDir = Yii::$app->assetManager->getPublishedUrl('@vendor/almasaeed2010/adminlte/dist');
$publishedRes = Yii::$app->assetManager->publish('@vendor/hail812/yii2-adminlte3/src/web/js');
$this->registerJsFile($publishedRes[1].'/control_sidebar.js', ['depends' => '\hail812\adminlte3\assets\AdminLteAsset']);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <?php $this->head() ?>
</head>
<body class="bg-[hsl(0_0%_10%_/_1)]">
<?php $this->beginBody() ?>

<!-- Sidebar Overlay для мобильных -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Main Admin Layout -->
<div class="flex h-screen admin-layout overflow-hidden" id="admin-layout-grid">
    <!-- Sidebar - Full Height Left -->
    <div class="flex-shrink-0 sidebar-wrapper h-full" id="sidebar-wrapper">
        <?= $this->render('sidebar', ['assetDir' => $assetDir]) ?>
    </div>

    <!-- Right Side: Header, Content -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <!-- Header -->
        <header class="flex-shrink-0 z-50">
            <?= $this->render('navbar', [
                'pageTitle' => $this->title,
                'headerActions' => $this->params['headerActions'] ?? null,
                'showFilters' => $this->params['showFilters'] ?? false,
            ]) ?>
        </header>

        <!-- Content and Filters Row (id нужен, чтобы при открытии фильтров на мобилке не обрезать панель) -->
        <div class="flex-1 flex min-h-0 overflow-hidden" id="content-and-filters-row">
            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto scrollbar-thin min-w-0 bg-[hsl(0_0%_10%_/_1)]">
                <?= $this->render('content', ['content' => $content, 'assetDir' => $assetDir]) ?>
            </main>

            <!-- Filters Panel: на десктопе — сайдбар справа, на мобилке — выдвижная панель (при открытии переносится в body, чтобы не обрезаться) -->
            <?php if (isset($this->params['showFilters']) && $this->params['showFilters']): ?>
            <div class="filters-drawer-backdrop" id="filters-drawer-backdrop" aria-hidden="true"></div>
            <div id="filters-drawer-slot">
            <aside class="filters-wrapper filters-drawer-aside flex-shrink-0 overflow-y-auto border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)]" id="filters-wrapper" style="width: 300px;">
                <?php
                $controllerId = Yii::$app->controller->id;
                $filtersFile = '@backend/views/' . $controllerId . '/filters-panel.php';
                $searchModel = $this->params['searchModel'] ?? null;
                if (file_exists(Yii::getAlias($filtersFile))) {
                    echo $this->render($filtersFile, ['searchModel' => $searchModel]);
                } else {
                    echo $this->render('filters-panel');
                }
                ?>
            </aside>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="modal-dialog" tabindex="-1" role="dialog" aria-labelledby="modal-dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <header class="flex items-center justify-between p-4 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h4 class="modal-title-js text-white font-semibold m-0"></h4>
                <button type="button" class="ds-btn ds-btn--icon ds-btn--ghost" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </header>
            <div class="modal-body-js p-4"></div>
        </div>
    </div>
</div>

<?php
$secondModal = new ModalWidget();
$secondModal->modalId = 'modal-dialog-second';
echo $secondModal->run();
?>

<!-- Page Preloader -->
<div class="page-preloader" id="page-preloader"></div>

<?php if (isset($this->params['showFilters']) && $this->params['showFilters']): ?>
<style>
/* Фильтры на мобилке: выдвижная панель (чистый CSS, без зависимости от Tailwind) */
.filters-drawer-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9998;
    opacity: 0;
    transition: opacity 0.2s ease;
    -webkit-tap-highlight-color: transparent;
}
body.filters-drawer-open .filters-drawer-backdrop {
    display: block;
    opacity: 1;
}
@media (max-width: 991px) {
    .filters-drawer-backdrop {
        display: block;
        pointer-events: none;
    }
    body.filters-drawer-open .filters-drawer-backdrop {
        pointer-events: auto;
    }
    .filters-wrapper.filters-drawer-aside {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 90vw !important;
        max-width: 320px !important;
        z-index: 9999 !important;
        transform: translateX(100%);
        transition: transform 0.25s ease-out;
        box-shadow: -4px 0 24px rgba(0,0,0,0.4);
    }
    body.filters-drawer-open .filters-wrapper.filters-drawer-aside {
        transform: translateX(0);
    }
    body.filters-drawer-open { overflow: hidden !important; }
    #filters-drawer-toggle {
        display: inline-flex !important;
        min-height: 44px;
        min-width: 44px;
        padding: 10px 14px;
        align-items: center;
        justify-content: center;
    }
    #filters-drawer-toggle .filters-btn-text { display: none; }
}
@media (max-width: 991px) and (min-width: 400px) {
    #filters-drawer-toggle .filters-btn-text { display: inline; }
}
@media (min-width: 992px) {
    .filters-drawer-backdrop { display: none !important; }
    #filters-drawer-toggle { display: none !important; }
    /* Панель фильтров на всю высоту */
    #content-and-filters-row {
        display: flex;
        align-items: stretch;
    }
    #filters-drawer-slot {
        display: flex;
        flex-direction: column;
        min-height: 0;
        height: 100%;
        align-self: stretch;
    }
    #filters-wrapper.filters-drawer-aside {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    #filters-wrapper .admin-filters-content {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('filters-drawer-toggle');
    var backdrop = document.getElementById('filters-drawer-backdrop');
    var wrapper = document.getElementById('filters-wrapper');
    var slot = document.getElementById('filters-drawer-slot');
    if (!btn || !backdrop || !wrapper) return;
    var isMobile = function() { return window.innerWidth < 992; };
    function openDrawer() {
        if (isMobile() && slot && wrapper.parentNode === slot) {
            document.body.appendChild(wrapper);
        }
        document.body.classList.add('filters-drawer-open');
        backdrop.setAttribute('aria-hidden', 'false');
    }
    function closeDrawer() {
        document.body.classList.remove('filters-drawer-open');
        backdrop.setAttribute('aria-hidden', 'true');
        if (slot && wrapper.parentNode === document.body) {
            slot.appendChild(wrapper);
        }
    }
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openDrawer();
    });
    backdrop.addEventListener('click', closeDrawer);
    document.body.addEventListener('click', function(e) {
        if (e.target && e.target.closest && e.target.closest('.filters-drawer-close')) closeDrawer();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('filters-drawer-open')) closeDrawer();
    });
    window.addEventListener('resize', function() {
        if (!isMobile() && wrapper.parentNode === document.body) {
            closeDrawer();
        }
    });
});
</script>
<?php endif; ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

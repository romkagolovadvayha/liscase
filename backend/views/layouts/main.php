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
<body class="admin-body">
<?php $this->beginBody() ?>

<a class="admin-skip-link" href="#admin-main-content">Перейти к содержимому</a>

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
        <?= $this->render('navbar', [
            'pageTitle' => $this->title,
            'headerActions' => $this->params['headerActions'] ?? null,
            'showFilters' => $this->params['showFilters'] ?? false,
        ]) ?>

        <!-- Content and Filters Row (id нужен, чтобы при открытии фильтров на мобилке не обрезать панель) -->
        <div class="flex-1 flex min-h-0 overflow-hidden" id="content-and-filters-row">
            <!-- Content Area -->
            <main class="admin-main-content flex-1 overflow-y-auto scrollbar-thin min-w-0" id="admin-main-content" tabindex="-1">
                <?= $this->render('content', ['content' => $content, 'assetDir' => $assetDir]) ?>
            </main>

            <!-- Filters Panel: на десктопе — сайдбар справа, на мобилке — выдвижная панель (при открытии переносится в body, чтобы не обрезаться) -->
            <?php if (isset($this->params['showFilters']) && $this->params['showFilters']): ?>
            <div class="filters-drawer-backdrop" id="filters-drawer-backdrop" aria-hidden="true"></div>
            <div id="filters-drawer-slot">
            <div class="filters-wrapper filters-drawer-aside flex-shrink-0 overflow-y-auto" id="filters-wrapper" role="complementary" aria-labelledby="filters-drawer-title" tabindex="-1">
                <h2 class="visually-hidden" id="filters-drawer-title">Фильтры страницы</h2>
                <div class="filters-drawer-header">
                    <span>Фильтры</span>
                    <button type="button" class="filters-drawer-close ds-btn ds-btn--icon ds-btn--ghost" aria-label="Закрыть фильтры">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <?php
                $controllerId = Yii::$app->controller->id;
                $filtersFile = '@backend/views/' . $controllerId . '/filters-panel.php';
                $searchModel = $this->params['searchModel'] ?? null;
                if (file_exists(Yii::getAlias($filtersFile))) {
                    echo $this->render($filtersFile, ['searchModel' => $searchModel]);
                } else {
                    echo $this->render('filters-panel', ['searchModel' => $searchModel]);
                }
                ?>
            </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<div class="modal fade" id="modal-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-dialog-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <header class="modal-header">
                <h2 class="modal-title modal-title-js" id="modal-dialog-title">Диалог</h2>
                <button type="button" class="ds-btn ds-btn--icon ds-btn--ghost" data-bs-dismiss="modal" aria-label="Закрыть окно">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="modal-body modal-body-js"></div>
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

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

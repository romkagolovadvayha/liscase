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
            ]) ?>
        </header>

        <!-- Content and Filters Row -->
        <div class="flex-1 flex min-h-0 overflow-hidden">
            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto scrollbar-thin min-w-0 bg-[hsl(0_0%_10%_/_1)]">
                <?= $this->render('content', ['content' => $content, 'assetDir' => $assetDir]) ?>
            </main>

            <!-- Filters Panel (optional, can be hidden) -->
            <?php if (isset($this->params['showFilters']) && $this->params['showFilters']): ?>
            <aside class="flex-shrink-0 overflow-y-auto scrollbar-thin hidden lg:block filters-wrapper border-l border-[hsl(0_0%_15.3%_/_1)]" id="filters-wrapper" style="width: 300px;">
                <?php 
                // Пробуем найти специфичный файл фильтров для текущего контроллера
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

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

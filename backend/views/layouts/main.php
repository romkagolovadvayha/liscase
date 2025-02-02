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
<body class="hold-transition sidebar-mini layout-fixed">
<?php $this->beginBody() ?>

<div class="wrapper">
    <?= $this->render('sidebar', ['assetDir' => $assetDir]) ?>
    <?= $this->render('content', ['content' => $content, 'assetDir' => $assetDir]) ?>
    <?= $this->render('control-sidebar') ?>
</div>


<div class="modal fade" id="modal-dialog" tabindex="-1" role="dialog" aria-labelledby="modal-dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <header class="flex justify-space-between items-center pb-28 pt-24 px-24 relative z-1 gap-x-24">
                <h4 class="short modal-title-js"></h4>
                <span class="icons icons_32px icons_32px_clear icons_hover" data-bs-dismiss="modal"></span>
            </header>
            <div class="modal-body-js"></div>
        </div>
    </div>
</div>
<?php
$secondModal          = new ModalWidget();
$secondModal->modalId = 'modal-dialog-second';
echo $secondModal->run();
?>

<div class="page-preloader"></div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

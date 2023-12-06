<?php

/** @var yii\web\View $this */

/** @var string $content */

use common\components\widgets\ModalWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

\backend\assets\AppAsset::register($this);
\common\assets\BootstrapIcons::register($this);
?>

<?php $this->beginPage() ?>
    <!DOCTYPE html>
    <html lang="<?= Yii::$app->language ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>
    </head>
    <body>
    <?php $this->beginBody() ?>

    <div class="wrap">
        <?php
        NavBar::begin(
            [
                'brandLabel'           => Yii::$app->name, 'brandUrl' => Yii::$app->homeUrl, 'options' => [
                'class' => 'navbar-expand-md navbar-dark bg-dark',
            ], 'innerContainerOptions' => [
                'class' => 'container container-full',
            ],
            ]
        );

        $menuItems = (new \backend\components\MainMenu())->getMenuItems();

        echo Nav::widget(
            [
                'options' => ['class' => 'navbar-nav ms-auto mb-2 mb-lg-0'], 'encodeLabels' => false,
                'items'   => $menuItems,
            ]
        );

        NavBar::end();
        ?>
        <?php if (isset($this->params['breadcrumbs'])): ?>
        <div class="breadcrumbs_wrap">
            <div class="container container-full">
                <?= \yii\bootstrap5\Breadcrumbs::widget(
                    [
                        'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                    ]
                ) ?>
            </div>
        </div>
        <?php endif; ?>
        <main id="main" class="flex-shrink-0" role="main">
            <div class="container container-full">
                <?= \backend\widgets\Alert::widget() ?>
                <?= $content; ?>
            </div>
        </main>
    </div>
    <?php
    echo ModalWidget::widget();

    $secondModal          = new ModalWidget();
    $secondModal->modalId = 'modal-dialog-second';
    echo $secondModal->run();
    ?>

    <div class="page-preloader"></div>

    <?php $this->endBody() ?>
    </body>
    </html>
<?php $this->endPage() ?>
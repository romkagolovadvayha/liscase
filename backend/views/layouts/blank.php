<?php
/* @var $this \yii\web\View */
/* @var $content string */
use yii\helpers\Html;

\backend\assets\FontAwesomeAsset::register($this);
\backend\assets\AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <?php $this->head() ?>
</head>
<body class="bg-[hsl(0_0%_10%_/_1)] min-h-screen">
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

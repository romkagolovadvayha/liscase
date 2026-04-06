<?php
// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/web.php',
    require __DIR__ . '/../config/web.php',
);

// REST не использует HTML-ассеты/minify из common; rmrevin View требует writable api/web/minify и ломает FPM при отказе mkdir
$config['components']['view'] = [
    'class' => \yii\web\View::class,
];

(new yii\web\Application($config))->run();


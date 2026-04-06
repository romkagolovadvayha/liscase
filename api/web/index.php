<?php
// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';

// До инициализации приложения: логи (FileTarget) и assets публикуют каталоги через mkdir — при владельце git и FPM под другим UID без этого сыпятся warning/500
$_apiRoot = dirname(__DIR__);
foreach ([
    $_apiRoot . '/runtime',
    $_apiRoot . '/runtime/logs',
    __DIR__ . '/assets',
] as $_ensureDir) {
    if (!is_dir($_ensureDir)) {
        @mkdir($_ensureDir, 0777, true);
    }
    if (is_dir($_ensureDir) && !is_writable($_ensureDir)) {
        @chmod($_ensureDir, 0777);
    }
}
unset($_apiRoot, $_ensureDir);

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/web.php',
    require __DIR__ . '/../config/web.php',
);

// REST не использует HTML-ассеты/minify из common; rmrevin View требует writable api/web/minify и ломает FPM при отказе mkdir
$config['components']['view'] = [
    'class' => \yii\web\View::class,
];

(new yii\web\Application($config))->run();


<?php

$params = require __DIR__ . '/../../common/config/params-local.php';
$db = require __DIR__ . '/../../common/config/db-local.php';

$configConsole = [
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'console\controllers',
    'vendorPath'     => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'assetManager'  => [
            'linkAssets'      => true,
            'appendTimestamp' => true,
        ],
        'authManager'   => [
            'class' => 'yii\rbac\PhpManager',
            'cache' => 'cache',
        ],
        'user'       => [
            'class'         => 'common\components\console\User',
            'identityClass' => 'common\models\user\User',
        ],
        'request' => ['class' => 'console\components\Request'],
        'db' => $db,
    ],
    'params' => $params,
];

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/web.php',
    $configConsole,
);

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require('common/config/queue.php'),
);
$config['components']['user'] = [
    'class'         => 'common\components\console\User',
    'identityClass' => 'common\models\user\User',
];
unset($config['components']['authManager']);
unset($config['components']['urlManager']);
unset($config['modules']['comment']);

return $config;

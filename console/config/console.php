<?php

$config = require __DIR__ . '/../../common/config/web.php';
$config['id'] = 'basic-console';
$config['bootstrap'] = [
    'log',
];
$config['basePath'] = dirname(__DIR__);
$config['controllerNamespace'] = 'console\controllers';
$config['components']['user'] = [
    'class'         => 'common\components\console\User',
    'identityClass' => 'common\models\user\User',
];
$config['modules']['translateManager'] = [
    'class'                   => 'DemonDogSL\translateManager\Module',
    'root'                    => [
        '@backend',
        '@frontend',
        '@common',
        '@console',
    ],
    'scanRootParentDirectory' => true,
    'ignoredCategories'       => ['yii', 'kvdrp'],
    'ignoredItems'            => ['assets', 'vendor'],
    'layout'                  => false,
    'allowedIPs'              => ['*'],
    'tables' => [
        [
            'connection' => 'db',
            'table' => '{{%box}}',
            'columns' => ['name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%drop}}',
            'columns' => ['quality', 'description', 'name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%drop_type}}',
            'columns' => ['name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%profit}}',
            'columns' => ['comment'],
        ],
    ],
    'scanners' => [
        '\DemonDogSL\translateManager\services\scanners\ScannerDatabase',
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpFunction',
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpArray',
        '\DemonDogSL\translateManager\services\scanners\ScannerJavaScriptFunction',
    ],
];

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require('common/config/queue.php'),
);

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;

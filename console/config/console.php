<?php

$params = require __DIR__ . '/../../common/config/params-local.php';
$db = require __DIR__ . '/../../common/config/db-local.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
    ],
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath'     => dirname(dirname(__DIR__)) . '/vendor',
    'modules'        => [
        'translateManager' => [
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
        ],
    ],
    'components' => [
        'assetManager'  => [
            'linkAssets'      => true,
            'appendTimestamp' => true,
        ],
        'i18n'          => [
            'translations' => [
                '*' => [
                    'class'              => 'yii\i18n\DbMessageSource',
                    'db'                 => 'db',
                    'sourceLanguage'     => 'ru-RU',
                    'sourceMessageTable' => '{{%language_source}}',
                    'messageTable'       => '{{%language_translate}}',
                    'enableCaching'      => true,
                    'cachingDuration'    => 86400,
                ],
            ],
        ],
        'user'       => [
            'class'         => 'common\components\console\User',
            'identityClass' => 'common\models\user\User',
        ],
        'urlManager' => [
            'hostInfo' => str_replace(['http://', 'https://', '/'], '', $params['baseUrl']),
            'baseUrl'  => $params['baseUrl'],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'request' => ['class' => 'console\components\Request'],
        'db' => $db,
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;

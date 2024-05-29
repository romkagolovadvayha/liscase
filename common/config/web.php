<?php

$params = require __DIR__ . '/params-local.php';
$db = require __DIR__ . '/db-local.php';
$dbSkindrops = require __DIR__ . '/db-skindrops-local.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        'translateManager' => [
            'class' => 'DemonDogSL\translateManager\Component'
        ]
    ],
    'language' => 'ru-RU',
    'sourceLanguage' => 'ru-RU',
    'name'       => 'PROSTOJ.STORE',
    'vendorPath'     => dirname(dirname(__DIR__)) . '/vendor',
    'modules'        => [
        'gridview' =>  [
            'class' => '\kartik\grid\Module'
        ],
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
            'layout'                  => '@backend/views/layouts/main',
            'roles'                   => ['ADMIN'],
            'allowedIPs'              => ['*']
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
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
        'translateManager' => [
            'class' => 'DemonDogSL\translateManager\Component'
        ],
        'redis'         => [
            'class'    => 'yii\redis\Connection',
            'hostname' => 'localhost',
            'port'     => 6379,
            'retries'  => 1,
        ],
        'cache'         => [
            'class'     => \yii\redis\Cache::class,
            'keyPrefix' => md5(dirname(__FILE__)),
        ],
        'user' => [
            'identityClass' => 'common\models\user\User',
            'enableAutoLogin' => true,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'db_server' => $db,
        'db_skindrops' => $dbSkindrops,
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules'           => [
                '/p/<refCode:\d+>'                 => '/',
            ],
        ],
        'authManager'   => [
            'class' => \yii\rbac\DbManager::class,
            'cache' => 'cache',
        ],
        'marketApi'   => [
            'class'  => \common\components\steam\MarketApi::class,
            'apiKey'      => '2gCOCfIiIu4V74f9763v5SjV7jyjT45',
            'baseUrl' => 'https://market.csgo.com/api/v2'
        ],
        'paypalychApi'   => [
            'class' => \common\components\payments\Paypalych::class,
            'secretKey' => '',
            'shop_id' => '',
        ],
        'tomeApi'   => [
            'class' => \common\components\payments\Tome::class,
            'secretKey' => 'ec6fbK60f9I74197f644X650A4B6b76PV1SH',
            'shop_id' => '002122',
        ],
        'freeKassaApi'   => [
            'class' => \common\components\payments\FreeKassa::class,
            'secretKey' => '3512023834c0f392047f0f2cbdd9d5a1',
            'shop_id' => '47799',
        ],
        'rustTm'   => [
            'class' => \common\components\rusttm\RustTm::class,
            'secretKey' => '4Tctry3D0b9003d52Kv2w10BND942mX',
        ],
        'curl'          => [
            'class' => \linslin\yii2\curl\Curl::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;

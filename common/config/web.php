<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language'       => 'ru-RU',
    'name'       => 'FOXDROP',
    'sourceLanguage' => 'ru-RU',
    'vendorPath'     => dirname(dirname(__DIR__)) . '/vendor',
    'modules'        => [
        'gridview' =>  [
            'class' => '\kartik\grid\Module'
        ]
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
            'rules'           => [],
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

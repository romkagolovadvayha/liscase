<?php

use yii\base\Component;

$params = require __DIR__ . '/../../common/config/params-local.php';
$db     = require __DIR__ . '/../../common/config/db-local.php';
$languages = [
    'en' => 'en-US',
    'ru' => 'ru-RU',
    'de' => 'de-DE',
    'uk' => 'uk-UA',
    'es' => 'es-ES',
];
$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'language' => 'ru-RU',
    'bootstrap' => [
        'log',
//        'languagepicker'
    ],
    'controllerNamespace' => 'api\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5c4cf22fbe90065a4a8e4591cf2cea84',
        ],
        'assetManager' => [
            'class' => 'yii\web\AssetManager',
            'forceCopy' => YII_DEBUG,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
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
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                'telegram-error' => [
                    'class' => 'common\components\log\TelegramSenderErrors',
                    'levels'  => ['error'],
                    'except'  => [
                        'yii\web\HttpException:403',
                        'yii\web\HttpException:404',
                        'yii\web\HttpException:400',
                        'EthereumRPC\Exception\ContractsException',
                        'yii\i18n\PhpMessageSource::loadFallbackMessages',
                    ],
                    'enabled' => true,
                    'exportInterval' => 1,
                ],
            ],
        ],
        'db' => $db,
        'authClientCollection' => [
            'class' => 'yii\authclient\Collection',
            'clients' => [
                'steam' => [
                    'class' => \common\components\oauth\Steam::class,
                    'key' => $params['steamApiKey'],
                ],
            ],
        ],
    ],
    'params' => $params,
];

$languages = [
    'en' => 'en-US',
    'ru' => 'ru-RU',
//    'de' => 'de-DE',
//    'uk' => 'uk-UA',
//    'es' => 'es-ES',
];
$config['params']['language'] = 'ru-RU';
$subDomain = explode('.', $_SERVER['HTTP_HOST'])[0];
$subDomain = str_replace(['https://', 'http://'], '', $subDomain);
if (in_array($subDomain, array_keys($languages))) {
    $config['language'] = $languages[$subDomain];
    $config['params']['language'] = $languages[$subDomain];
}
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
$config['params']['homePage'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
return $config;

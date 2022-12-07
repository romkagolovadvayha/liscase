<?php

use yii\base\Component;

$params = require __DIR__ . '/../../common/config/params-local.php';
$db     = require __DIR__ . '/../../common/config/db-local.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'languagepicker'],
    'controllerNamespace' => 'frontend\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5c4cf22fbe90065a4a8e4591cf2cea84',
        ],
        'languagepicker'       => [
            'class'      => 'common\components\web\LanguagePickerComponent',
            'cookieName' => 'language-picker',
            'languages'  => [
                'ru-RU' => 'RU',
                'en-US' => 'EN',
                'de-DE' => 'DE',
                'it-IT' => 'IT',
                'es-ES' => 'ES',
                'fr-FR' => 'FR',
                'vi-VN' => 'VN',
                'id-ID' => 'ID',
                'hi-IN' => 'HI',
                'pt-PT' => 'PT',
                'tr-TR' => 'TR',
                'hr-HR' => 'HR',
                //                'nl-NL' => 'NL',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'assetManager' => [
            'class' => 'yii\web\AssetManager',
            'forceCopy' => YII_DEBUG,
        ],
        'user' => [
            'identityClass'   => 'common\models\user\User',
            'loginUrl'        => ['auth/oauth?authclient=steam'],
            'enableAutoLogin' => true,
            'identityCookie'  => [
                'name'   => '_identity',
                'domain' => $params['cookieDomain'],
                'httpOnly' => true,
            ],
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

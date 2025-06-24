<?php

$params = require __DIR__ . '/../../common/config/params-local.php';
$db = require __DIR__ . '/../../common/config/db-local.php';

$config = [
    'id' => 'basic',
    'name' => 'LisCase',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'backend\controllers',
    'defaultRoute'        => 'user/index',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'hostInfo' => str_replace(['http://', 'https://', '/'], '', $params['backendUrl']),
            'baseUrl'  => $params['backendUrl'],
        ],
        'view' => [
            'class' => '\rmrevin\yii\minify\View',
            //'enableMinify' => !YII_DEBUG,
            'concatCss' => true, // concatenate css
            'minifyCss' => true, // minificate css
            'concatJs' => true, // concatenate js
            'minifyJs' => true, // minificate js
            'minifyOutput' => true, // minificate result html page
            'webPath' => '@web', // path alias to web base
            'basePath' => '@webroot', // path alias to web base
            'minifyPath' => '@webroot/minify', // path alias to save minify result
            'jsPosition' => [ \yii\web\View::POS_END ], // positions of js files to be minified
            'forceCharset' => 'UTF-8', // charset forcibly assign, otherwise will use all of the files found charset
            'expandImports' => true, // whether to change @import on content
            'compressOptions' => ['extra' => true], // options for compress
            'excludeFiles' => [],
            'excludeBundles' => [],
            'renderers' => [
                'twig' => [
                    'class' => 'yii\twig\ViewRenderer',
                    'cachePath' => '@runtime/Twig/cache',
                    // Array of twig options:
                    'options' => [
                        'auto_reload' => true,
                    ],
                    'globals' => [
                        'html' => ['class' => '\yii\helpers\Html'],
                        'url' => ['class' => '\yii\helpers\Url'],
                    ],
                    'uses' => ['yii\bootstrap'],
                ],
                // ...
            ],
        ],
        'assetManager' => [
            'class' => 'yii\web\AssetManager',
            'forceCopy' => YII_DEBUG,
        ],
        'user'         => [
            'identityClass'   => 'common\models\user\User',
            'loginUrl'        => ['auth/index'],
            'enableAutoLogin' => true,
            'identityCookie'  => [
                'name'   => '_identity',
                'domain' => $params['cookieDomain'],
                'httpOnly' => true,
            ],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5c4cf22fbe90065a4a8e4591cf2cea84',
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
                    'class' => \yii\log\FileTarget::class,
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
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
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

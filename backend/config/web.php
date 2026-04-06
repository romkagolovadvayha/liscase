<?php

$params = require __DIR__ . '/../../common/config/params-local.php';
$db = require __DIR__ . '/../../common/config/db-local.php';

$config = [
    'id' => 'basic',
    'name' => 'LisCase',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'dbTemplateBootstrap', 'minifyPathBootstrap'],
    'controllerNamespace' => 'backend\controllers',
    'defaultRoute'        => 'user/index',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'dbTemplates' => [
            'class' => \common\components\template\DbTemplateService::class,
        ],
        'dbTemplateBootstrap' => [
            'class' => \common\components\template\DbTemplateBootstrap::class,
        ],
        'minifyPathBootstrap' => [
            'class' => \common\components\bootstrap\MinifyPathBootstrap::class,
        ],
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'hostInfo' => str_replace(['http://', 'https://', '/'], '', $params['backendUrl']),
            'baseUrl'  => $params['backendUrl'],
        ],
        'assetManager' => [
            'class' => 'yii\web\AssetManager',
            'forceCopy' => true, // всегда копировать ассеты заново, чтобы после деплоя не отдавался старый main.min.css
            'appendTimestamp' => true, // Автоматически добавляет timestamp к ассетам для инвалидации кэша
        ],
        'view' => [
            'class' => '\rmrevin\yii\minify\View',
            'enableMinify' => false,
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
            'enableCsrfValidation' => !YII_ENV_DEV, // Отключаем CSRF валидацию в dev, включаем в prod
            'csrfCookie' => array_merge([
                'httpOnly' => true,
                'secure'   => !YII_ENV_DEV, // Только для прода (HTTPS), для dev (HTTP) - false
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ], !empty($params['cookieDomain']) ? ['domain' => $params['cookieDomain']] : []),
        ],
        'session' => [
            'cookieParams' => array_merge([
                'httpOnly' => true,
                'secure'   => !YII_ENV_DEV, // Только для прода (HTTPS), для dev (HTTP) - false
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ], !empty($params['cookieDomain']) ? ['domain' => $params['cookieDomain']] : []),
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
    // Только если пакеты из require-dev установлены (на проде часто composer --no-dev + забытый YII_ENV=dev)
    if (class_exists(\yii\debug\Module::class)) {
        $config['bootstrap'][] = 'debug';
        $config['modules']['debug'] = [
            'class' => \yii\debug\Module::class,
            // uncomment the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ];
    }

    if (class_exists(\yii\gii\Module::class)) {
        $config['bootstrap'][] = 'gii';
        $config['modules']['gii'] = [
            'class' => \yii\gii\Module::class,
            // uncomment the following to add your IP if you are not connecting from localhost.
            //'allowedIPs' => ['127.0.0.1', '::1'],
        ];
    }
}

return $config;

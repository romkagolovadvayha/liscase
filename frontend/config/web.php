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
//        'log',
//        'languagepicker'
          'dbTemplateBootstrap',
    ],
    'controllerNamespace' => 'frontend\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'modules'    => [
        'webhook' => [
            'class' => 'frontend\modules\webhook\Module',
        ],
    ],
    'components' => [
        'dbTemplates' => [
            'class' => \common\components\template\DbTemplateService::class,
        ],
        'dbTemplateBootstrap' => [
            'class' => \common\components\template\DbTemplateBootstrap::class,
        ],
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules'           => [
                '/p/<refCode:\d+>'                 => '/',
                'posts' => 'blog/index',
                'posts/<categoryLinkName:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/?' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/' => 'blog/category',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/' => 'blog/category',
                '/servers/wipe-block' => '/servers/wipe-block',
                '/servers/tag-<tagLink:[a-z0-9_-]+>' => '/servers/tag',
                '/servers/<serverTag:[a-z0-9_-]+>/' => '/stats/stats',
                '/servers/<serverTag:[a-z0-9_-]+>/<steamId:[0-9]+>/' => '/stats/player-new',
                '/servers/<serverTag:[a-z0-9_-]+>/<steamId:[0-9]+>/report' => '/stats/report',
                '/servers/<serverTag:[a-z0-9_-]+>/rules' => '/servers/rules',
                '/servers/<serverTag:[a-z0-9_-]+>/wipe-info' => '/servers/wipe-info',
                '/maps/vote' => '/maps/vote',
                '/maps/get-likes' => '/maps/get-likes',
                '/maps/<serverTag:[a-z0-9_-]+>/' => '/maps',
                'sitemap.xml' => 'site/sitemap',
                'sitemap-main.xml' => 'site/sitemap-main',
                'sitemap-servers.xml' => 'site/sitemap-servers',
                'sitemap-posts.xml' => 'site/sitemap-posts',
                'sitemap-radio.xml' => 'site/sitemap-radio',
                'robots.txt' => 'site/robots',
                'rss' => 'site/rss',
                'radio' => 'radio/index',
                'radio/<id:\d+>' => 'radio/station',
                'db-asset/<root>/<path:.+>' => 'db-asset/serve',
            ],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5c4cf22fbe90065a4a8e4591cf2cea84',
            'csrfCookie' => [
                'httpOnly' => true,
                'secure'   => true,
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'session' => [
            'cookieParams' => [
                'httpOnly' => true,
                'secure'   => true,
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
//        'languagepicker'       => [
//            'class'      => 'common\components\web\LanguagePicker',
//            'cookieName' => 'language-picker',
//            'languages'  => [
//                'en-US' => 'EN',
//                'ru-RU' => 'RU',
//                'de-DE' => 'DE',
//                'it-IT' => 'IT',
//                'es-ES' => 'ES',
//                'fr-FR' => 'FR',
//                'vi-VN' => 'VN',
//                'id-ID' => 'ID',
//                'hi-IN' => 'HI',
//                'pt-PT' => 'PT',
//                'tr-TR' => 'TR',
//                'hr-HR' => 'HR',
//            ],
//        ],
        'assetManager' => [
            'class' => 'yii\web\AssetManager',
            'forceCopy' => YII_DEBUG,
            'appendTimestamp' => true,
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
}
$config['params']['homePage'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
return $config;

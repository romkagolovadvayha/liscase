<?php

$params = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);
$db = require __DIR__ . '/db-local.php';

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
                '@api',
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
        'comment' => [
            'class' => 'yii2mod\comments\Module',
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
        'view' => [
            'class' => '\rmrevin\yii\minify\View',
            'enableMinify' => !YII_DEBUG,
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
        'db_server' => $db,
        /*
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        */
        'authManager'   => [
            'class' => \yii\rbac\DbManager::class,
            'cache' => 'cache',
        ],
        'marketApi'   => [
            'class'  => \common\components\steam\MarketApi::class,
            'apiKey'      => '2gCOCfIiIu4V74f9763v5SjV7jyjT45',
            'baseUrl' => 'https://market.csgo.com/api/v2'
        ],
        'telegramPayments'   => [
            'class'  => \common\components\telegram\TelegramPayments::class,
        ],
        'telegramReports'   => [
            'class'  => \common\components\telegram\TelegramReports::class,
        ],
        'telegramChats'   => [
            'class'  => \common\components\telegram\TelegramChats::class,
        ],
        'telegramSupport'   => [
            'class'  => \common\components\telegram\TelegramSupport::class,
        ],
        'telegramReport'   => [
            'class'  => \common\components\telegram\TelegramReport::class,
        ],
        'paypalychApi'   => [
            'class' => \common\components\payments\Paypalych::class,
            'secretKey' => '',
            'shop_id' => '',
        ],
        'tomeApi'   => [
            'class' => \common\components\payments\Tome::class,
        ],
        'freeKassaApi'   => [
            'class' => \common\components\payments\FreeKassa::class,
        ],
        'anyPayApi'   => [
            'class' => \common\components\payments\AnyPay::class,
        ],
        'settings'   => [
            'class' => \common\components\settings\Settings::class,
        ],
        'rustTm'   => [
            'class' => \common\components\rusttm\RustTm::class,
        ],
        'csGoMarket'   => [
            'class' => \common\components\rusttm\CsGoMarket::class,
        ],
        'rustCheck'   => [
            'class' => \common\components\rustcheck\RustCheck::class,
        ],
        'discord'   => [
            'class' => \common\components\discord\Discord::class,
        ],
        'wargm'   => [
            'class' => \common\components\wargm\WarGM::class,
            'apiKey' => $params['wargmApiKey'],
            'baseUrl' => 'https://api.wargm.ru/v1',
        ],
        'curl'          => [
            'class' => \linslin\yii2\curl\Curl::class,
        ],
        'openAi'   => [
            'class'  => \common\components\openAi\OpenAiApi::class,
            'apiKey'      => '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',
        ],
        'personalBotTelegram'               => [
            'class'    => \common\components\telegram\TelegramApiHelper::class,
        ],
        'rustotekaBotTelegram'               => [
            'class'    => \common\components\telegram\TelegramApiHelper::class,
        ],
        'midjourney'   => [
            'class'  => \common\components\midjourney\MidjourneyApi::class,
            'discordChannelId'      => '1150211599395737601',
            'discordUserToken'      => 'MTE1MDIxMDQ4MTI2NTU5MDI5Mg.GZhegP.iSny8xdLjtgnETPDiiYygmJr4sHVu_hjEA-5R0',
        ],
        'openAiSupport' => [
            'class' => \common\components\openAi\OpenAiSupport::class,
            'model' => 'gpt-4o-mini', // или gpt-3.5-turbo
        ],
        's3Api'   => [
            'class'  => \common\components\storage\S3Api::class,
            'baseUrl'      => 'https://s3.timeweb.cloud',
            'accessKey'      => 'N6NBZ5Y5B4O28MRGS1FJ',
            'secretAccessKey'      => 'iN1mkKooMdqTlClrvMDRnEw70ms9tzrNIHeRSsia',
            'swift'      => 'https://swift.timeweb.cloud',
            'swiftSecretAccessKey'      => 'X5wkuU5OMu9Xl7gJ1TJu0YbLCsMUaKq5uD1AgTuM',
            'uid'      => '66961113-07e73532-2c3b-4dfb-8909-6eb71b2c6593',
            'region'      => 'ru-1',
        ],
    ],
    'params' => $params,
];

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require('queue.php'),
);

//if (YII_ENV_DEV) {
//    // configuration adjustments for 'dev' environment
//    $config['bootstrap'][] = 'debug';
//    $config['modules']['debug'] = [
//        'class' => 'yii\debug\Module',
//        // uncomment the following to add your IP if you are not connecting from localhost.
//        //'allowedIPs' => ['127.0.0.1', '::1'],
//    ];
//
//    $config['bootstrap'][] = 'gii';
//    $config['modules']['gii'] = [
//        'class' => 'yii\gii\Module',
//        // uncomment the following to add your IP if you are not connecting from localhost.
//        //'allowedIPs' => ['127.0.0.1', '::1'],
//    ];
//}

return yii\helpers\ArrayHelper::merge(
    $config,
    require __DIR__ . '/../../common/config/web-local.php',
);

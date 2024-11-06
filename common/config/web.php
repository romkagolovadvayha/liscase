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
                'posts' => 'blog/index',
                'posts/<categoryLinkName:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/?' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/?' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/?' => 'blog/category',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/?' => 'blog/category',
                'sitemap.xml' => 'site/sitemap',
                'robots.txt' => 'site/robots',
                'rss' => 'site/rss',
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
        'telegramPayments'   => [
            'class'  => \common\components\telegram\TelegramPayments::class,
            'token'      => $params['tgBot']['token'],
            'chatId' => $params['tgBot']['chatId'],
        ],
        'telegramReports'   => [
            'class'  => \common\components\telegram\TelegramReports::class,
            'token'      => $params['tgBotReports']['token'],
            'chatId' => $params['tgBotReports']['chatId'],
        ],
        'telegramChats'   => [
            'class'  => \common\components\telegram\TelegramChats::class,
            'token'      => $params['tgBotChats']['token'],
            'chatId' => $params['tgBotChats']['chatId'],
        ],
        'paypalychApi'   => [
            'class' => \common\components\payments\Paypalych::class,
            'secretKey' => '',
            'shop_id' => '',
        ],
        'tomeApi'   => [
            'class' => \common\components\payments\Tome::class,
            'secretKey' => 'e85QE8144b3W4Obe77109cdaGdffBV116R0e',
            'shop_id' => '001399',
        ],
        'freeKassaApi'   => [
            'class' => \common\components\payments\FreeKassa::class,
            'secretKey' => '3512023834c0f392047f0f2cbdd9d5a1',
            'shop_id' => '47799',
        ],
        'anyPayApi'   => [
            'class' => \common\components\payments\AnyPay::class,
            'secretKey' => '3yhUPXUOl5Ub1k3NN5whTVQVqLYXkYqTYXVuRCj',
            'shop_id' => '15080',
            'api_id' => '133583606621D793DD',
        ],
        'rustTm'   => [
            'class' => \common\components\rusttm\RustTm::class,
            'secretKey' => '4Tctry3D0b9003d52Kv2w10BND942mX',
            //'secretKey' => 'y6aq0Jb1WmFe2nMcYy1y41Iq0V4lpq3',
        ],
        'rustCheck'   => [
            'class' => \common\components\rustcheck\RustCheck::class,
            'secretKey' => '9f97841bde0d594447c732dc2311a465',
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
            'apiKey'      => 'sk-proj-amY8H17pQlMQYaH0b6qRT3BlbkFJ9ZKbYA0IPZs14RSAEfNb',
        ],
        'personalBotTelegram'               => [
            'class'    => \common\components\telegram\TelegramApiHelper::class,
            'botToken' => '7005949610:AAEK2H_vhym6px9mPB4EMyeZ94Hgx71sxzM', //@ProstojServerBot
        ],
        'rustotekaBotTelegram'               => [
            'class'    => \common\components\telegram\TelegramApiHelper::class,
            'botToken' => '7494504343:AAFL_vGF1V7o5a4SRWvniY-R7NZ6pUqYa8M', //@rustoteka_bot
        ],
        'midjourney'   => [
            'class'  => \common\components\midjourney\MidjourneyApi::class,
            'discordChannelId'      => '1150211599395737601',
            'discordUserToken'      => 'MTE1MDIxMDQ4MTI2NTU5MDI5Mg.GZhegP.iSny8xdLjtgnETPDiiYygmJr4sHVu_hjEA-5R0',
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

return $config;

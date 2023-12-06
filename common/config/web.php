<?php

$params = require __DIR__ . '/params-local.php';
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
    'language' => 'en-US',
    'sourceLanguage' => 'ru-RU',
    'name'       => 'WARCRAFT.PRO',
    'vendorPath'     => dirname(dirname(__DIR__)) . '/vendor',
    'modules'        => [
        'gridview' =>  [
            'class' => '\kartik\grid\Module'
        ],
        'translateManager' => [
            'class'                   => DemonDogSL\translateManager\Module::class,
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
            'allowedIPs'              => ['*'],
            'tables' => [
                [
                    'connection' => 'db',
                    'table' => '{{%blog}}',
                    'columns' => ['name', 'content', 'keywords', 'description'],
                    'category' => 'database',
                ],
                [
                    'connection' => 'db',
                    'table' => '{{%blog_category}}',
                    'columns' => ['name', 'keywords', 'description'],
                    'category' => 'database',
                ],
                [
                    'connection' => 'db',
                    'table' => '{{%comment}}',
                    'columns' => ['content'],
                    'category' => 'database',
                ],
                [
                    'connection' => 'db',
                    'table' => '{{%user_profile}}',
                    'columns' => ['full_name'],
                    'category' => 'database',
                ],
                [
                    'connection' => 'db',
                    'table' => '{{%settings}}',
                    'columns' => ['text'],
                    'category' => 'database',
                ]
            ],
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
                    'enableCaching'      => false,
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
                'posts' => 'blog/index',
                'posts/<categoryLinkName:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/?' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/post-<blogLinkName:[a-z0-9_-]+>/?' => 'blog/view',
                'posts/<categoryLinkName:[a-z0-9_-]+>/?' => 'blog/category',
                'posts/<categoryLinkName:[a-z0-9_-]+>/<categoryLinkNameChild:[a-z0-9_-]+>/?' => 'blog/category',
                'users/<username:[a-z0-9_-]+>/?' => 'users/view',
                '/css/colors.css' => '/settings/colors',
                'sitemap.xml' => 'site/sitemap',
                'robots.txt' => 'site/robots',
                'rss' => 'site/rss',
            ],
        ],
        'authManager'   => [
            'class' => \yii\rbac\DbManager::class,
            'cache' => 'cache',
        ],
        'openAi'   => [
            'class'  => \common\components\openAi\OpenAiApi::class,
            'apiKey'      => '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',
        ],
        'midjourney'   => [
            'class'  => \common\components\midjourney\MidjourneyApi::class,
            'discordChannelId'      => '1150211599395737601',
            'discordUserToken'      => 'MTE1MDIxMDQ4MTI2NTU5MDI5Mg.GZhegP.iSny8xdLjtgnETPDiiYygmJr4sHVu_hjEA-5R0',
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

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require('queue.php'),
);

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

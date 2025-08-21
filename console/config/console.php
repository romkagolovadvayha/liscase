<?php

$config = require __DIR__ . '/../../common/config/web.php';
$config['id'] = 'basic-console';
$config['bootstrap'] = [
    'log',
    'crontask',
];
$config['basePath'] = dirname(__DIR__);
$config['controllerNamespace'] = 'console\controllers';
$config['components']['user'] = [
    'class'         => 'common\components\console\User',
    'identityClass' => 'common\models\user\User',
];
$config['components']['request'] = ['class' => 'console\components\Request'];
$config['components']['log'] = [
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
];
$config['modules']['crontask'] = [
    'class'    => 'gofmanaa\crontask\Module',
    'fileName' => 'cron.txt',
    'yiiPath'  => __DIR__ . '/../../yii',
    'tasks'    => [
        'supportCheckClosed'       => [
            'command' => 'support/check-closed',
            'min'     => '*/5',
        ],
    ]
];
$config['components']['crontask'] = [
    'class' => \console\components\CrontabSafe::class,
];
$config['controllerMap']['crontask'] = [
    'class' => \gofmanaa\crontask\console\CronController::class,
];
$config['modules']['translateManager'] = [
    'class'                   => \DemonDogSL\translateManager\Module::class,
    'root'                    => [
        '@frontend',
        '@common',
    ],
//    'scanRootParentDirectory' => true,
//    'ignoredCategories'       => ['yii', 'kvdrp'],
//    'ignoredItems'            => ['assets', 'vendor'],
//    'layout'                  => false,
    'patterns'                  => ['*.php', '*.js', '*.twig'],
    'allowedIPs'              => ['*'],
    'tables' => [
        [
            'connection' => 'db',
            'table' => '{{%box}}',
            'columns' => ['name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%drop}}',
            'columns' => ['description', 'name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%select}}',
            'columns' => ['description', 'name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%sets}}',
            'columns' => ['description', 'name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%category}}',
            'columns' => ['name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%servers}}',
            'columns' => ['name', 'description', 'rules', 'monitoring_name', 'monitoring_description'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%blog}}',
            'columns' => ['name', 'description', 'keywords', 'content'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%blog_category}}',
            'columns' => ['name', 'description', 'keywords'],
        ],
//        [
//            'connection' => 'db',
//            'table' => '{{%comment}}',
//            'columns' => ['content'],
//        ],
        [
            'connection' => 'db',
            'table' => '{{%task}}',
            'columns' => ['description'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%drop_type}}',
            'columns' => ['name'],
        ],
        [
            'connection' => 'db',
            'table' => '{{%site_settings}}',
            'columns' => ['value'],
            'where' => 'is_translate = 1',
        ],
    ],
    'scanners' => [
        \common\components\scanners\ScannerTwigTemplate::class,
        common\components\scanners\ScannerDatabase::class,
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpFunction',
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpArray',
        '\DemonDogSL\translateManager\services\scanners\ScannerJavaScriptFunction',
    ],
];

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require(__DIR__ . '/../../common/config/queue.php'),
);

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;

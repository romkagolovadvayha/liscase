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
$config['components']['supervisor'] = [
    'class' => \console\components\Supervisor::class,
    'ctl'   => '/usr/bin/supervisorctl', // проверь путь
    'sudo'  => true,                     // если нужен sudo
    // 'config' => '/etc/supervisor/supervisord.conf',
    // 'socket' => 'unix:///run/supervisor.sock',
];
$config['components']['supervisortask'] = [
    'class' => \console\components\Supervisortask::class,
];
$config['modules']['crontask'] = [
    'class'    => 'console\components\CrontaskModuleSafe',
    'fileName' => 'cron.txt',
    'yiiPath'  => __DIR__ . '/../../yii',
    'tasks'    => [
        'supportCheckClosed'       => [
            'command' => 'support/check-closed',
            'min'     => '*/5',
        ],
        'supportCheck'       => [
            'command' => 'support/check',
            'min'     => '*',
        ],
        'clanRefreshActiveMembersCache' => [
            'command' => 'clan/refresh-active-members-cache',
            'min'     => '*/5',
        ],
        /** Полный пересчёт клановой статистики по серверам (после пакетов stats в statistics). */
        'clanUpdateStatistics' => [
            'command' => 'clan/update-statistics',
            'min'     => '*/15',
        ],
        'userUpdate'       => [
            'command' => 'user/update',
            'min'     => '*',
        ],
        'storageUpdateTops'       => [
            'command' => 'storage/update-tops',
            'min'     => '*/5',
        ],
        /** Глобальные рекорды (/records): GET /v1/stats/global-records, TTL 48 ч. */
        'statsActivePlayersCache' => [
            'command' => 'stats/active-players-cache',
            'hour'    => '*/12',
            'min'     => '20',
        ],
        'storageUpdateMarket'       => [
            'command' => 'storage/update-market',
            'min'     => '*/20',
        ],
        'storageUpdate'       => [
            'command' => 'storage/update',
            'min'     => '*/2',
        ],
        'storageCalculateTops'       => [
            'command' => 'storage/calculate-tops',
            'min'     => '*/7',
        ],
        'skinDropsStatusCheck'       => [
            'command' => 'skin-drops/status-check',
            'min'     => '*',
        ],
        'skinDropsGoDraw'       => [
            'command' => 'skin-drops/go-draw',
            'hour'     => '*',
            'min'     => '0',
        ],
        'serverReport'       => [
            'command' => 'server/report',
            'hour'     => '0',
            'min'     => '1',
        ],
        'serverStatusCheck'       => [
            'command' => 'server/check-status',
            'min'     => '*',
        ],
        'serverWsOnline'       => [
            'command' => 'server-ws/online',
            'min'     => '*',
        ],
        'rustotekaBanImport'       => [
            'command' => 'rustoteka/ban-import',
            'min'     => '11',
        ],
        'depositSync'       => [
            'command' => 'deposit/sync',
            'min'     => '*/3',
        ],
        'getCsGoFiles'       => [
            'command' => 'support/empty && cd /var/www/www-root/data/var/www/prostoj.store/frontend/web/uploads/prices && wget https://market.csgo.com/api/v2/prices/class_instance/RUB.json -O csmarket.json && cd /var/www/www-root/data/var/www/prostoj.store && ./yii storage/update-price-cs-go',
            'min'     => '*/7',
        ],
        'getRustFiles'       => [
            'command' => 'support/empty && cd /var/www/www-root/data/var/www/prostoj.store/frontend/web/uploads/prices && wget https://rust.tm/api/v2/prices/class_instance/RUB.json -O rusttm.json',
            'min'     => '*/3',
        ],
        'marketSkinsSync'       => [
            'command' => 'market-skins/sync',
            'min'     => '*/10',
        ],
        'getApproved'       => [
            'command' => 'skin-drops/get-approved',
            'hour'     => '6',
        ],
        'discordRolesCheckExpiredVip'       => [
            'command' => 'discord-roles/check-expired-vip',
            'hour'     => '3',
            'min'     => '0',
        ],
    ]
];
$config['controllerMap']['supervisortask'] = [
    'class' =>\console\controllers\SupervisortaskController::class,
];

$config['modules']['translateManager'] = [
    'class'                   => \DemonDogSL\translateManager\Module::class,
    'root'                    => [
        '@frontend',
        '@common',
        '@api',
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
            'where' => 'created_at > "2025-05-01 00:00:01"',
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
        [
            'connection' => 'db',
            'table' => '{{%servers_tags}}',
            'columns' => ['name', 'title', 'short_description', 'description'],
        ],
    ],
    'ignoredItems' => [
        'vendor',
        'runtime',
        'web/assets',
        'assets',
        'node_modules',
        'bower_components',
        'storage',
        'uploads',
        'tests',
        '.git',
        '.idea',
        'dist',
        'build',
        'cache',
        'tmp',
    ],
    'scanners' => [
        \common\components\scanners\ScannerDbTemplates::class,
        \common\components\scanners\ScannerTwigTemplate::class,
        \common\components\scanners\ScannerDatabase::class,
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpFunction',
        '\DemonDogSL\translateManager\services\scanners\ScannerPhpArray',
        '\DemonDogSL\translateManager\services\scanners\ScannerJavaScriptFunction',
    ],
];

$config = yii\helpers\ArrayHelper::merge(
    $config,
    require(__DIR__ . '/../../common/config/queue.php'),
);

if (YII_ENV_DEV && class_exists(\yii\gii\Module::class)) {
    // gii в require-dev; при composer --no-dev класса нет — не подключаем
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
}

unset($config['components']['comment']);
unset($config['modules']['comment']);

return $config;

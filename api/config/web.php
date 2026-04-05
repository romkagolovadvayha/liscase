<?php

use yii\base\Component;

// Убеждаемся что Yii загружен (для тестов)
if (!class_exists('Yii', false)) {
    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
}

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
        'api\components\LanguageBootstrap', // Yii::$app->language из куки NEXT_LOCALE / Accept-Language / ?language= для Yii::t()
    ],
    'controllerNamespace' => 'api\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'modules' => [
        'swagger' => [
            'class' => 'api\modules\swagger\Module',
        ],
    ],
    'components' => [
        'jwt' => [
            'class' => 'api\components\jwt\JwtService',
            'secret' => $params['jwt']['secret'] ?? getenv('JWT_SECRET'),
            'algorithm' => $params['jwt']['algorithm'] ?? 'HS256',
            'expiration' => $params['jwt']['expiration'] ?? 3600,
            'refreshExpiration' => $params['jwt']['refreshExpiration'] ?? 604800,
        ],
        'urlManager'    => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules' => [
                // Swagger
                'swagger' => 'swagger/index/index',
                'swagger/json' => 'swagger/index/json',

                // API v1 routes
                // Auth
                'v1/auth/oauth' => 'v1/auth/oauth',
                'v1/auth/callback' => 'v1/auth/callback',
                'v1/auth/login' => 'v1/auth/login',
                'v1/auth/refresh' => 'v1/auth/refresh',
                'v1/auth/logout' => 'v1/auth/logout',
                'v1/auth/me' => 'v1/auth/me',
                'v1/auth/discord' => 'v1/auth/discord',
                'v1/auth/discord-callback' => 'discord/callback',
                'v1/auth/twitch' => 'v1/auth/twitch',
                'v1/auth/twitch-callback' => 'twitch/callback',
                'v1/auth/kick' => 'v1/auth/kick',
                'v1/auth/kick-callback' => 'kick/callback',

                // User
                'v1/user/profile' => 'v1/user/profile',
                'v1/user/current-server' => 'v1/user/current-server',
                'v1/user/social-links' => 'v1/user/social-links',
                'v1/user/balance' => 'v1/user/balance',
                'v1/user/history' => 'v1/user/history',
                'v1/user/operations' => 'v1/user/operations',
                'v1/user/skins-balance' => 'v1/user/skins-balance',
                'v1/user/skins-statistics' => 'v1/user/skins-statistics',
                'v1/user/skins-operations' => 'v1/user/skins-operations',
                'v1/user/sell-drop' => 'v1/user/sell-drop',
                'v1/user/transfer' => 'v1/user/transfer',
                'v1/user/partner' => 'v1/user/partner',
                'v1/user/partner/conditions' => 'v1/user/partner-conditions',
                'v1/user/partner/invite' => 'v1/user/partner-invite',
                'v1/user/partner/referrals' => 'v1/user/partner-referrals',
                'v1/user/partner/promocode' => 'v1/user/partner-promocode',
                'v1/user/partner-bonus/<id:\d+>' => 'v1/user/partner-bonus',
                'v1/user/partner-bonus' => 'v1/user/partner-bonus',
                'v1/user/promocode' => 'v1/user/promocode',
                'v1/user/promocode/activate' => 'v1/user/activate-promocode',

                // Clans (просмотр без JWT; управление — с Bearer JWT)
                /** GET /v1/clans/list?ip=&port= — плоский JSON-массив для плагина Rust (без обёртки success/data) */
                'v1/clans/list' => 'v1/clans/game-plugin-list',
                'v1/clans/permissions' => 'v1/clans/permissions',
                'v1/clans/rankings' => 'v1/clans/rankings',
                'v1/clans/podium' => 'v1/clans/podium',
                'v1/clans/my-invites' => 'v1/clans/my-invites',
                'v1/clans/my-memberships' => 'v1/clans/my-memberships',
                /** GET /v1/clans/lookup-global?slug= — карточка по ЧПУ без serverTag (суффикс -id глобально уникален) */
                'v1/clans/lookup-global' => 'v1/clans/lookup-global',
                /** Полный ЧПУ-сегмент (например my-clan-12): точное совпадение, без ошибочного разбора id */
                'v1/clans/<serverTag:[\w-]+>/lookup' => 'v1/clans/lookup-by-slug',
                'v1/clans/invite-link/<token:[a-fA-F0-9]+>' => 'v1/clans/invite-link-preview',
                ['pattern' => 'v1/clans/invite-link/<token:[a-fA-F0-9]+>/join', 'route' => 'v1/clans/invite-link-join', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/invites/<inviteId:\d+>/accept', 'route' => 'v1/clans/accept-invite', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/invites/<inviteId:\d+>/decline', 'route' => 'v1/clans/decline-invite', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans', 'route' => 'v1/clans/create', 'verb' => ['POST', 'OPTIONS']],
                'v1/clans' => 'v1/clans/index',
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/logo', 'route' => 'v1/clans/upload-logo', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/invite-links/<linkId:\d+>', 'route' => 'v1/clans/invite-links-delete', 'verb' => ['DELETE', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/invite-links', 'route' => 'v1/clans/invite-links-create', 'verb' => ['POST', 'OPTIONS']],
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/invite-links' => 'v1/clans/invite-links-list',
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/apply', 'route' => 'v1/clans/apply', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/applications/<appId:\d+>/accept', 'route' => 'v1/clans/application-accept', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/applications/<appId:\d+>/reject', 'route' => 'v1/clans/application-reject', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/applications', 'route' => 'v1/clans/applications-list', 'verb' => ['GET', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/posts/<postId:\d+>', 'route' => 'v1/clans/post-delete', 'verb' => ['DELETE', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/posts/<postId:\d+>', 'route' => 'v1/clans/post-update', 'verb' => ['PATCH', 'PUT', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/posts', 'route' => 'v1/clans/post-create', 'verb' => ['POST', 'OPTIONS']],
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/posts' => 'v1/clans/posts-list',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/statistics/member/<memberId:\d+>' => 'v1/clans/member-statistics',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/player-kills' => 'v1/clans/player-kills',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/statistics' => 'v1/clans/statistics',
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/members/<memberId:\d+>/trust-review', 'route' => 'v1/clans/member-trust-review', 'verb' => ['GET', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/members/<memberId:\d+>/permissions', 'route' => 'v1/clans/member-permissions', 'verb' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS']],
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/members' => 'v1/clans/members',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/history' => 'v1/clans/history',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/achievements' => 'v1/clans/achievements',
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>/invites' => 'v1/clans/invites-list',
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/invite', 'route' => 'v1/clans/invite', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/leave', 'route' => 'v1/clans/leave', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/kick', 'route' => 'v1/clans/kick', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/promote', 'route' => 'v1/clans/promote', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/demote', 'route' => 'v1/clans/demote', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/transfer-leadership', 'route' => 'v1/clans/transfer-leadership', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>/privacy', 'route' => 'v1/clans/privacy', 'verb' => ['PATCH', 'PUT', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>', 'route' => 'v1/clans/delete', 'verb' => ['DELETE', 'OPTIONS']],
                ['pattern' => 'v1/clans/<serverTag:[\w-]+>/<id:\d+>', 'route' => 'v1/clans/update', 'verb' => ['PATCH', 'PUT', 'OPTIONS']],
                'v1/clans/<serverTag:[\w-]+>/<id:\d+>' => 'v1/clans/view',

                // Settings
                'v1/settings' => 'v1/settings/index',
                ['pattern' => 'v1/avatar-frames/select', 'route' => 'v1/avatar-frame/select', 'verb' => ['POST', 'OPTIONS']],
                'v1/avatar-frames' => 'v1/avatar-frame/index',

                // Translations (i18n для фронта — один запрос все ключи)
                'v1/translations/report-missing' => 'v1/translations/report-missing',
                'v1/translations/new-front' => 'v1/translations/new-front',
                'v1/translations' => 'v1/translations/index',

                // Skins
                'v1/skins' => 'v1/skins/index',
                'v1/skins/giveaway' => 'v1/skins/giveaway',
                'v1/skins/skindrops' => 'v1/skins/skindrops',
                ['pattern' => 'v1/skins/<id:\d+>/buy', 'route' => 'v1/skins/buy', 'verb' => ['POST', 'OPTIONS']],
                'v1/skins/confirm/<id:\d+>' => 'v1/skins/confirm',

                // Products
            'v1/products' => 'v1/products/index',
            'v1/products/categories' => 'v1/products/categories',
            'v1/products/<id:\d+>/favorite' => 'v1/products/toggle-favorite',
            'v1/products/<id:\d+>' => 'v1/products/view',
            'v1/products/<id:\d+>/buy' => 'v1/products/buy',
            'v1/banlist' => 'v1/banlist/index',

                // Servers
                'v1/servers' => 'v1/servers/index',
                'v1/servers/wipe-block/items' => 'v1/servers/wipe-block-items',
                'v1/servers/wipe-block' => 'v1/servers/wipe-block',
                'v1/servers/<tag:[\w-]+>' => 'v1/servers/view',
                'v1/servers/tag/<tagLink:[\w-]+>' => 'v1/servers/tag',
                'v1/servers/<serverTag:[\w-]+>/rules' => 'v1/servers/rules',
                'v1/servers/<serverTag:[\w-]+>/wipe-info' => 'v1/servers/wipe-info',
                'v1/servers/stats' => 'v1/stats/stats', // публичный, без обязательной авторизации

                // Stats
                'v1/stats' => 'v1/stats/stats',
                'v1/stats/players' => 'v1/stats/players',
                'v1/stats/player/<steamId:\d+>' => 'v1/stats/player-new',
                'v1/stats/player-resources' => 'v1/stats/player-resources',
                'v1/stats/player-kills' => 'v1/stats/player-kills',
                'v1/stats/player-team' => 'v1/stats/player-team',
                'v1/stats/player-loot-crafts' => 'v1/stats/player-loot-crafts',
                'v1/stats/duels' => 'v1/stats/duels',
                'v1/stats/search' => 'v1/stats/search',
                'v1/stats/tops' => 'v1/stats/tops',
                'v1/stats/global-records' => 'v1/stats/global-records',
                'v1/stats/server-players-table' => 'v1/stats/server-players-table',
                'v1/stats/personal' => 'v1/stats/personal',
                'v1/stats/report/<serverTag:[\w-]+>/<steamId:\d+>' => 'v1/stats/report',

                // Servers Statistics History
                'v1/servers-statistics-history/month' => 'v1/servers-statistics-history/month',
                'v1/servers-statistics-history/week' => 'v1/servers-statistics-history/week',
                'v1/servers-statistics-history/day' => 'v1/servers-statistics-history/day',

                // Tasks
                'v1/tasks' => 'v1/tasks/index',
                'v1/tasks/<id:\d+>' => 'v1/tasks/detail',
                'v1/tasks/<id:\d+>/check' => 'v1/tasks/check',

                // Payment
                'v1/payment/methods' => 'v1/payment/methods',
                'v1/payment/create' => 'v1/payment/create',
                'v1/payment/status/<id:\d+>' => 'v1/payment/status',
                'v1/payment/callback/<payment:[\w-]+>' => 'v1/payment/callback',

                // Support
                'v1/support/tickets' => 'v1/support/tickets',
                'v1/support/tickets/create' => 'v1/support/create',
                'v1/support/tickets/<id:\d+>' => 'v1/support/view',
                'v1/support/tickets/<id:\d+>/messages/<messageId:\d+>' => 'v1/support/update-message', // Более специфичный маршрут должен быть первым
                'v1/support/tickets/<id:\d+>/messages' => 'v1/support/send',
                'v1/support/tickets/<id:\d+>/close' => 'v1/support/close',
                'v1/support/tickets/<id:\d+>/open' => 'v1/support/open',
                'v1/support/users/<userId:\d+>/mute' => 'v1/support/mute',
                'v1/support/users/<userId:\d+>/block-chat' => 'v1/support/block-chat',
                'v1/support/users/<userId:\d+>/block-account' => 'v1/support/block-account',

                // Support GameStores (для плагина GameStoresRUST)
                'v1/support-game-stores/tickets' => 'v1/support-game-stores/tickets',
                'v1/support-game-stores/create' => 'v1/support-game-stores/create',
                'v1/support-game-stores/tickets/<id:[\w-]+>' => 'v1/support-game-stores/view', // ID может быть не только числом
                'v1/support-game-stores/tickets/<id:[\w-]+>/messages' => 'v1/support-game-stores/send',

                // Store (с авторизацией: список предметов, категорий, возврат, выдача на сервер)
                'v1/store/items' => 'v1/store/items',
                'v1/store/categories' => 'v1/store/categories',
                'v1/store/deliver' => 'v1/store/deliver',
                'v1/store/return' => 'v1/store/return',

                // Blog
                'v1/blog/categories' => 'v1/blog/categories',
                'v1/blog/<linkName>/similar' => 'v1/blog/similar',
                'v1/blog/<linkName>/comments' => 'v1/blog/comments',
                'v1/blog/comments/<id:\d+>/like' => 'v1/blog/like-comment',
                'v1/blog' => 'v1/blog/index',

                // Wipe Calendar
                'v1/wipe-calendar' => 'v1/wipe-calendar/index',
                'v1/wipe-calendar/server' => 'v1/wipe-calendar/server',

                // Maps
                'v1/maps/vote' => 'v1/maps/vote',
                'v1/maps/<id:\d+>' => 'v1/maps/detail',
                'v1/maps' => 'v1/maps/index',

                // Radio
                'v1/radio/list' => 'v1/radio/list',
                /** GET /v1/radio/boombox-list — строка для BoomBox.ServerUrlList (UsersOnline) */
                'v1/radio/boombox-list' => 'v1/radio/boombox-list',

                // Ingest статистики с игровых серверов (POST raw JSON, без JWT; как frontend ApiStatsController)
                ['pattern' => 'v1/plugin-ingest/update-users/<serverTag:[\w-]+>', 'route' => 'stats/update-users', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/plugin-ingest/raid/<serverTag:[\w-]+>', 'route' => 'stats/raid', 'verb' => ['POST', 'OPTIONS']],
                ['pattern' => 'v1/plugin-ingest/signs', 'route' => 'stats/signs', 'verb' => ['POST', 'OPTIONS']],

                // Конфиг плагинов из панели (явный v1-префикс)
                'v1/rust-plugin-config/get' => 'rust-plugin-config/get',

                // Тексты wipe / welcome / help для плагинов (плоский JSON)
                'v1/rust-plugin-chat/wipe/<serverTag:[\w-]+>' => 'v1/rust-plugin-chat/wipe',
                'v1/rust-plugin-chat/welcome/<serverTag:[\w-]+>' => 'v1/rust-plugin-chat/welcome',
                'v1/rust-plugin-chat/help/<serverTag:[\w-]+>' => 'v1/rust-plugin-chat/help',

                // Legacy API магазина ProstojRUST (?secret=&method=…)
                'v1/rust-legacy-store' => 'v1/rust-legacy-store/index',

                // Raid Table
                'v1/raid-table' => 'v1/raid-table/index',

                // Referral
                'v1/referral' => 'v1/referral/index',

                // Buildings
                'v1/buildings' => 'v1/buildings/index',
                'v1/buildings/upload-image' => 'v1/buildings/upload-image',
                'v1/buildings/<id:\d+>/like' => 'v1/buildings/like',
                'v1/buildings/<id:\d+>/likes' => 'v1/buildings/likes',
                ['pattern' => 'v1/buildings/<id:\d+>/leave-resident', 'route' => 'v1/buildings/leave-resident', 'verb' => ['POST']],
                ['pattern' => 'v1/buildings/<id:\d+>', 'route' => 'v1/buildings/delete', 'verb' => ['DELETE']],
                'v1/buildings/<id:\d+>' => 'v1/buildings/view',

                // Custom Skins
                'v1/custom-skins/categories' => 'v1/custom-skins/categories',
                'v1/custom-skins/<id:\d+>/likes' => 'v1/custom-skins/likes',
                'v1/custom-skins/<id:\d+>/like' => 'v1/custom-skins/like',
                'v1/custom-skins' => 'v1/custom-skins/index',

                // User Videos (Медиа)
                'v1/user-videos/streamers' => 'v1/user-video/streamers',
                'v1/user-videos/create' => 'v1/user-video/create',
                'v1/user-videos/<id:\d+>/like' => 'v1/user-video/like',
                'v1/user-videos/<id:\d+>/likes' => 'v1/user-video/likes',
                'v1/user-videos' => 'v1/user-video/index',

                // GameStores API (для плагина GameStoresRUST)
                // Отдельный роутинг для store.pluginInfo (с точкой)
                'v1/game-stores/<method:[\w\.-]+>' => 'v1/game-stores/index',
                
                // Прямые вызовы методов GameStores (для обратной совместимости)
                // Поддерживаем форматы: baskets.item, baskets.bySteamId, store.pluginInfo и т.д.
                // Используем паттерн который захватывает метод целиком включая точку
                'v1/<method:baskets\.[\w]+>' => 'v1/game-stores/index',
                'v1/<method:store\.[\w]+>' => 'v1/game-stores/index',
                'v1/<method:server\.[\w]+>' => 'v1/game-stores/index',

                // GameStores Payments API (для платежей через плагин)
                'v1/integrations/payments/custom' => 'v1/game-stores/integrations-payments-custom',

                // HighlightCaptureMod (Rust PvP киллы → Telegram)
                'v1/rust/highlights' => 'v1/rust-highlights/index',
            ],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5c4cf22fbe90065a4a8e4591cf2cea84',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
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
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/app.log',
                    'maxFileSize' => 10240, // 10MB
                    'maxLogFiles' => 5,
                    'categories' => ['application', 'yii\*', 'api\*'],
                    'logVars' => ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'],
                ],
                'telegram-error' => [
                    'class' => 'common\components\log\TelegramSenderErrors',
                    'levels' => ['error'],
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

// Проверяем наличие HTTP_HOST только если это веб-запрос
if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
    $subDomain = explode('.', $_SERVER['HTTP_HOST'])[0];
    $subDomain = str_replace(['https://', 'http://'], '', $subDomain);
    if (in_array($subDomain, array_keys($languages))) {
        $config['language'] = $languages[$subDomain];
        $config['params']['language'] = $languages[$subDomain];
    }
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

// Устанавливаем homePage только если доступны необходимые переменные
if (isset($_SERVER['REQUEST_SCHEME']) && isset($_SERVER['HTTP_HOST'])) {
    $config['params']['homePage'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
} else {
    $config['params']['homePage'] = $params['homePage'] ?? 'http://localhost';
}

// Хосты, на которые разрешён редирект после OAuth (привязка Twitch/Discord/Kick). По умолчанию — фронт + локальная разработка.
if (empty($config['params']['allowedRedirectHosts'])) {
    $hosts = ['localhost', '127.0.0.1', 'prostoj.local'];
    $frontendUrl = $config['params']['frontendUrl'] ?? $params['frontendUrl'] ?? null;
    if (!empty($frontendUrl) && ($h = parse_url($frontendUrl, PHP_URL_HOST))) {
        $hosts[] = $h;
    }
    $currentHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if (strpos($currentHost, 'api.') === 0) {
        $hosts[] = substr($currentHost, 4);
    }
    $config['params']['allowedRedirectHosts'] = array_values(array_unique($hosts));
}

return $config;

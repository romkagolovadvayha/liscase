<?php
$params = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

$dbHost = getenv('DB_HOST') ?: '';
$dbName = getenv('DB_NAME') ?: ($params['db']['name'] ?? 'liscase');
$dbUser = getenv('DB_USER') ?: ($params['db']['user'] ?? 'root');
$dbPassword = getenv('DB_PASSWORD') ?: ($params['db']['password'] ?? '');

// Если DB_HOST не задан - используем SQLite (для тестовых/демо деплоев)
if (empty($dbHost)) {
    return [
        'class' => 'yii\db\Connection',
        'dsn' => 'sqlite:' . dirname(dirname(__DIR__)) . '/runtime/db.sqlite',
        'charset' => 'utf8',
    ];
}

// Иначе используем MySQL
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=' . $dbHost . ';port=3306;dbname=' . $dbName,
    'username' => $dbUser,
    'password' => $dbPassword,
    'charset' => 'utf8',
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

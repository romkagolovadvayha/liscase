<?php
$params = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=' . $params['db']['host'] . ';dbname=' . $params['db']['name'],
    'username' => $params['db']['user'],
    'password' => $params['db']['password'],
    'charset' => 'utf8',
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];

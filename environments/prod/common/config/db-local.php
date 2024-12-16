<?php

return [
    'class' => 'yii\db\Connection',
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'dsn' => 'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'),
    'charset' => 'utf8',
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 60,
    'schemaCache' => 'cache',
];

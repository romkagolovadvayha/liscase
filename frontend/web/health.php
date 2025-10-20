<?php
// Health check endpoint для мониторинга
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'OK',
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
]);


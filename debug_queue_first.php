<?php
/**
 * Отладка POST /api/queue/add-first
 */

echo "🔍 Debugging /api/queue/add-first\n";
echo str_repeat("=", 60) . "\n\n";

$port = 8081;
$stationId = 1;
$filename = 'test.mp3';

// 1. Проверка что Node.js вообще работает
echo "1️⃣ Checking if Node.js is running...\n";
$statusUrl = "http://localhost:{$port}/api/status";
$statusResponse = @file_get_contents($statusUrl);

if ($statusResponse === false) {
    echo "   ❌ Node.js NOT responding at {$statusUrl}\n";
    echo "   💡 Start Node.js: cd node\\mode && start-radio-1.bat\n";
    exit(1);
}

echo "   ✅ Node.js is running!\n\n";

// 2. Тест с минимальным payload
echo "2️⃣ Testing POST /api/queue/add-first...\n";
$addUrl = "http://localhost:{$port}/api/queue/add-first";

$payload = json_encode(['track' => $filename]);
echo "   URL: {$addUrl}\n";
echo "   Payload: {$payload}\n";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                    "Content-Length: " . strlen($payload) . "\r\n",
        'content' => $payload,
        'timeout' => 5,
        'ignore_errors' => true,
    ]
]);

echo "   Sending request...\n";
$response = @file_get_contents($addUrl, false, $context);

if ($response === false) {
    echo "   ❌ NO RESPONSE from Node.js!\n";
    echo "   Error: " . error_get_last()['message'] . "\n";
    
    // Проверяем что endpoint существует
    echo "\n3️⃣ Trying GET on the same path (should fail with 404 or method not allowed)...\n";
    $getResponse = @file_get_contents($addUrl);
    if ($getResponse === false) {
        echo "   ❌ GET also failed - endpoint may not exist!\n";
    } else {
        echo "   ✅ GET responded (endpoint exists but POST doesn't work)\n";
        echo "   Response: {$getResponse}\n";
    }
    
    exit(1);
}

echo "   ✅ Response received!\n";
echo "   Response: {$response}\n\n";

$data = json_decode($response, true);

if ($data && isset($data['success'])) {
    if ($data['success']) {
        echo "🎉 SUCCESS! Track added to queue!\n";
    } else {
        echo "⚠️  Node.js responded but success=false\n";
        echo "   Error: " . ($data['error'] ?? 'unknown') . "\n";
    }
} else {
    echo "⚠️  Invalid JSON response\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Debug complete!\n";


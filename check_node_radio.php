<?php
/**
 * Простая проверка Node.js радио серверов
 */

echo "🔍 Checking Node.js Radio Servers\n";
echo str_repeat("=", 60) . "\n\n";

$stations = [
    ['id' => 1, 'name' => 'Radio Station #1', 'port' => 8081],
    ['id' => 2, 'name' => 'Radio Station #2', 'port' => 8082],
];

foreach ($stations as $station) {
    echo "📻 {$station['name']}\n";
    echo "   Port: {$station['port']}\n";
    
    // Проверяем Node.js API
    $apiUrl = "http://localhost:{$station['port']}/api/status";
    echo "   Checking: {$apiUrl}\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true,
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        echo "   ❌ Node.js NOT RESPONDING!\n";
        echo "   💡 Start with: cd node\\mode && start-radio-{$station['id']}.bat\n\n";
        continue;
    }
    
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'running') {
        echo "   ✅ Node.js is running!\n";
        echo "   👥 Listeners: " . ($data['listeners'] ?? 0) . "\n";
        echo "   📋 Queue length: " . ($data['queueLength'] ?? 'N/A') . "\n";
        
        if (!empty($data['current']['name'])) {
            echo "   🎵 Now playing: " . basename($data['current']['name']) . "\n";
        }
        
        // Тест добавления трека
        echo "\n   🧪 Testing POST /api/queue/add...\n";
        $testTrack = 'test.mp3';
        
        $addContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['track' => $testTrack]),
                'timeout' => 2,
                'ignore_errors' => true,
            ]
        ]);
        
        $addUrl = "http://localhost:{$station['port']}/api/queue/add";
        $addResponse = @file_get_contents($addUrl, false, $addContext);
        
        if ($addResponse) {
            $addData = json_decode($addResponse, true);
            if ($addData && isset($addData['success'])) {
                if ($addData['success']) {
                    echo "   ✅ API test: SUCCESS! (file may not exist, but API works)\n";
                } else {
                    echo "   ⚠️  API responded: " . ($addData['error'] ?? 'unknown error') . "\n";
                }
            } else {
                echo "   ❌ API test: Invalid response\n";
            }
        } else {
            echo "   ❌ API test: No response\n";
        }
    } else {
        echo "   ⚠️  Node.js responded but status != running\n";
        echo "   Response: {$response}\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Check complete!\n\n";
echo "💡 If servers are not running:\n";
echo "   1. cd node\\mode\n";
echo "   2. start-radio-1.bat\n";
echo "   3. start-radio-2.bat\n";


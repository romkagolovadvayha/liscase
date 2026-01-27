<?php
/**
 * Проверка статуса радиостанций
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/common/config/bootstrap.php';
require __DIR__ . '/backend/config/bootstrap.php';

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/common/config/main.php',
    require __DIR__ . '/common/config/main-local.php',
    require __DIR__ . '/backend/config/main.php',
    require __DIR__ . '/backend/config/main-local.php'
);

$application = new yii\web\Application($config);

echo "🔍 Checking Radio Stations Status\n";
echo str_repeat("=", 60) . "\n\n";

// Получаем все станции
$stations = \common\models\radio\RadioStation::find()->all();

if (empty($stations)) {
    echo "❌ No radio stations found in database!\n";
    echo "   Run: php yii migrate\n\n";
    exit(1);
}

foreach ($stations as $station) {
    echo "📻 Station #{$station->id}: {$station->name}\n";
    echo "   Port: {$station->port}\n";
    echo "   Status in DB: " . ($station->is_running ? '✅ Running' : '⭕ Stopped') . "\n";
    echo "   Folder: {$station->getFolderPath()}\n";
    
    // Проверяем Node.js API
    $apiUrl = "http://localhost:{$station->port}/api/status";
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
        echo "   💡 Start with: cd node\\mode && start-radio-{$station->id}.bat\n";
    } else {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'running') {
            echo "   ✅ Node.js is running!\n";
            echo "   👥 Listeners: " . ($data['listeners'] ?? 0) . "\n";
            echo "   📋 Queue length: " . ($data['queueLength'] ?? 0) . "\n";
            
            if (!empty($data['current']['name'])) {
                echo "   🎵 Now playing: " . basename($data['current']['name']) . "\n";
            }
        } else {
            echo "   ⚠️  Node.js responded but status != running\n";
            echo "   Response: {$response}\n";
        }
    }
    
    // Проверяем треки
    $tracksCount = \common\models\radio\RadioTrack::find()
        ->where(['radio_station_id' => $station->id, 'status' => \common\models\radio\RadioTrack::STATUS_ACTIVE])
        ->count();
    
    echo "   💿 Approved tracks: {$tracksCount}\n";
    
    // Проверяем папку с файлами
    $folderPath = $station->getFolderPath();
    if (!file_exists($folderPath)) {
        echo "   ⚠️  Folder does not exist: {$folderPath}\n";
        echo "   Creating...\n";
        @mkdir($folderPath, 0777, true);
    } else {
        $mp3Files = glob($folderPath . '/*.mp3');
        echo "   📂 MP3 files in folder: " . count($mp3Files) . "\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Check complete!\n";


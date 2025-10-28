<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;

/**
 * Radio initialization controller
 */
class RadioInitController extends Controller
{
    /**
     * Initialize radio stations and settings
     */
    public function actionIndex()
    {
        echo "🎵 Initializing Radio Stations...\n\n";

        // Check if tables exist
        try {
            $exists = Yii::$app->db->createCommand("SHOW TABLES LIKE 'radio_station'")->queryScalar();
            if (!$exists) {
                echo "❌ Error: Table 'radio_station' does not exist!\n";
                echo "Please run migrations first: php yii migrate\n";
                return 1;
            }
        } catch (\Exception $e) {
            echo "❌ Error checking tables: " . $e->getMessage() . "\n";
            return 1;
        }

        // Add radio stations
        echo "📻 Adding radio stations...\n";
        
        try {
            $station1 = Yii::$app->db->createCommand()->insert('radio_station', [
                'id' => 1,
                'name' => 'Радио #1',
                'description' => 'Первая радиостанция сервера',
                'port' => 8081,
                'folder_name' => '1',
                'status' => 1,
                'is_running' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
            
            echo "  ✅ Added: Радио #1 (port 8081)\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "  ⏭️  Радио #1 already exists\n";
            } else {
                echo "  ⚠️  Error: " . $e->getMessage() . "\n";
            }
        }

        try {
            $station2 = Yii::$app->db->createCommand()->insert('radio_station', [
                'id' => 2,
                'name' => 'Радио #2',
                'description' => 'Вторая радиостанция сервера',
                'port' => 8082,
                'folder_name' => '2',
                'status' => 1,
                'is_running' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
            
            echo "  ✅ Added: Радио #2 (port 8082)\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "  ⏭️  Радио #2 already exists\n";
            } else {
                echo "  ⚠️  Error: " . $e->getMessage() . "\n";
            }
        }

        // Enable section in settings
        echo "\n⚙️  Enabling radio section in settings...\n";
        
        try {
            $exists = Yii::$app->db->createCommand(
                "SELECT value FROM site_settings WHERE code = 'section_radio'"
            )->queryScalar();

            if ($exists) {
                Yii::$app->db->createCommand()
                    ->update('site_settings', ['value' => '1'], ['code' => 'section_radio'])
                    ->execute();
                echo "  ✅ Updated: section_radio = 1\n";
            } else {
                Yii::$app->db->createCommand()
                    ->insert('site_settings', [
                        'name' => 'Раздел радиостанций',
                        'category' => 'site',
                        'type' => 'checkbox',
                        'value' => '1',
                        'code' => 'section_radio',
                    ])
                    ->execute();
                echo "  ✅ Added: section_radio = 1\n";
            }
        } catch (\Exception $e) {
            echo "  ⚠️  Error: " . $e->getMessage() . "\n";
        }

        // Show current state
        echo "\n📊 Current radio stations:\n";
        $stations = Yii::$app->db->createCommand("SELECT * FROM radio_station")->queryAll();
        foreach ($stations as $station) {
            echo "  - ID: {$station['id']}, Name: {$station['name']}, Port: {$station['port']}, Folder: {$station['folder_name']}\n";
        }

        echo "\n📊 Settings:\n";
        $setting = Yii::$app->db->createCommand(
            "SELECT * FROM site_settings WHERE code = 'section_radio'"
        )->queryOne();
        if ($setting) {
            echo "  - section_radio = {$setting['value']}\n";
        } else {
            echo "  - section_radio not found\n";
        }

        echo "\n✅ Initialization complete!\n";
        echo "\n📝 Next steps:\n";
        echo "  1. Create folders: mkdir -p node/mode/sounds/1 node/mode/sounds/2\n";
        echo "  2. Set permissions: chmod -R 777 node/mode/sounds/\n";
        echo "  3. Start radio #1: cd node/mode/sounds/1 && PORT=8081 node ../../app.js\n";
        echo "  4. Start radio #2: cd node/mode/sounds/2 && PORT=8082 node ../../app.js\n";
        echo "  5. Open in browser: http://your-domain/radio\n";

        return 0;
    }
}


<?php

namespace console\controllers;

use common\models\map\Map;
use common\models\map\UserMap;
use yii\console\Controller;

/**
 * Maps fix controller
 */
class MapsFixController extends Controller
{
    /**
     * Синхронизирует поле votes в таблице map с реальным количеством голосов из user_map
     * 
     * Использование:
     * php yii maps-fix/sync-votes
     */
    public function actionSyncVotes()
    {
        echo "Starting votes synchronization...\n";
        
        $maps = Map::find()->all();
        $updated = 0;
        $skipped = 0;
        
        foreach ($maps as $map) {
            // Считаем реальное количество голосов из user_map
            $realVotes = UserMap::find()
                ->where(['map_id' => $map->id, 'vote' => 1])
                ->count();
            
            // Если не совпадает - обновляем
            if ($map->getAttribute('votes') != $realVotes) {
                $oldVotes = $map->getAttribute('votes');
                $map->setAttribute('votes', $realVotes);
                if ($map->save(false)) {
                    echo "Map ID {$map->id}: {$oldVotes} -> {$realVotes} votes\n";
                    $updated++;
                } else {
                    echo "ERROR: Failed to update Map ID {$map->id}\n";
                }
            } else {
                $skipped++;
            }
        }
        
        echo "\nSynchronization completed!\n";
        echo "Updated: {$updated} maps\n";
        echo "Skipped: {$skipped} maps (already correct)\n";
        
        return 0;
    }
}


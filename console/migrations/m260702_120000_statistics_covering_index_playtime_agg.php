<?php

use console\components\migration\Migration;

/**
 * Покрывающие индексы для stats/active-players-cache (шаг 1: SUM(playtime) по steam_id).
 *
 * @see \console\controllers\StatsController::actionActivePlayersCache
 */
class m260702_120000_statistics_covering_index_playtime_agg extends Migration
{
    public function safeUp()
    {
        $globalExists = $this->db->createCommand("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = 'statistics'
            AND index_name = 'idx_statistics_key_steam_value'
        ")->queryScalar();

        if (!$globalExists) {
            $this->execute("
                CREATE INDEX idx_statistics_key_steam_value
                ON statistics (`key`, steam_id, value)
            ");
        }

        $serverExists = $this->db->createCommand("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = 'statistics'
            AND index_name = 'idx_statistics_key_server_steam_value'
        ")->queryScalar();

        if (!$serverExists) {
            $this->execute("
                CREATE INDEX idx_statistics_key_server_steam_value
                ON statistics (`key`, server_tag, steam_id, value)
            ");
        }
    }

    public function safeDown()
    {
        $this->dropIndexIfExists('idx_statistics_key_steam_value', 'statistics');
        $this->dropIndexIfExists('idx_statistics_key_server_steam_value', 'statistics');
    }

    protected function dropIndexIfExists($name, $table)
    {
        try {
            $indexExists = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'")->queryOne();
            if ($indexExists) {
                $this->dropIndex($name, $table);
            }
        } catch (\Exception $e) {
            // ignore
        }
    }
}

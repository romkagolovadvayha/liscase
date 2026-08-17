<?php

use console\components\migration\Migration;

/**
 * Covers the hot "recent online players per server" scans used by server
 * monitoring, ping aggregation and online-only cache preparation.
 */
class m260817_120000_optimize_online_player_queries extends Migration
{
    private const INDEX = 'idx_user_server_status_last_visit';

    public function safeUp()
    {
        if (!$this->indexExists()) {
            $this->createIndex(
                self::INDEX,
                'user',
                ['server_id', 'status', 'last_visit_server_at']
            );
        }
    }

    public function safeDown()
    {
        if ($this->indexExists()) {
            $this->dropIndex(self::INDEX, 'user');
        }
    }

    private function indexExists(): bool
    {
        return (bool) $this->db->createCommand(
            'SELECT 1 FROM information_schema.statistics'
            . ' WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
            . ' LIMIT 1',
            [':table' => 'user', ':index' => self::INDEX]
        )->queryScalar();
    }
}

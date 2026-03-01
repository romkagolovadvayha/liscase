<?php

use console\components\migration\Migration;

/**
 * Уникальный индекс для таблицы statistics: пакетный upsert в UpdateStatsUsersJob
 * (steam_id, server_tag, wipe, key) — одна строка на комбинацию.
 */
class m260225_120000_add_statistics_unique_for_upsert extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableName = $this->db->schema->getRawTableName('{{%statistics}}');
        $indexName = 'idx_statistics_steam_server_wipe_key';

        $exists = $this->db->createCommand("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND index_name = :index
        ", [
            ':table' => $tableName,
            ':index' => $indexName,
        ])->queryScalar();

        if (!$exists) {
            // key — зарезервированное слово в MySQL
            $this->execute("
                CREATE UNIQUE INDEX {$indexName}
                ON {$tableName} (steam_id, server_tag, wipe, `key`)
            ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $tableName = $this->db->schema->getRawTableName('{{%statistics}}');
        $indexName = 'idx_statistics_steam_server_wipe_key';
        try {
            $this->execute("DROP INDEX {$indexName} ON {$tableName}");
        } catch (\Exception $e) {
            // ignore
        }
    }
}

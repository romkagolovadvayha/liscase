<?php

use console\components\migration\Migration;

/**
 * Уникальный индекс для таблицы statistics: пакетный upsert в UpdateStatsUsersJob
 * (steam_id, server_tag, wipe, key) — одна строка на комбинацию.
 * Перед созданием индекса объединяем дубликаты (на случай если миграция не была применена вовремя).
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

        if ($exists) {
            return;
        }

        // Сначала объединяем дубликаты: одна строка на (steam_id, server_tag, wipe, key), value = SUM(value)
        $transaction = $this->db->beginTransaction();
        try {
            $this->db->createCommand("
                CREATE TEMPORARY TABLE _stat_merged
                SELECT steam_id, server_tag, `key`, SUM(value) AS value, wipe
                FROM {$tableName}
                GROUP BY steam_id, server_tag, wipe, `key`
            ")->execute();

            $this->db->createCommand("DELETE FROM {$tableName}")->execute();

            $this->db->createCommand("
                INSERT INTO {$tableName} (steam_id, server_tag, `key`, value, wipe)
                SELECT steam_id, server_tag, `key`, value, wipe FROM _stat_merged
            ")->execute();

            $this->db->createCommand('DROP TEMPORARY TABLE _stat_merged')->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        // key — зарезервированное слово в MySQL
        $this->execute("
            CREATE UNIQUE INDEX {$indexName}
            ON {$tableName} (steam_id, server_tag, wipe, `key`)
        ");
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

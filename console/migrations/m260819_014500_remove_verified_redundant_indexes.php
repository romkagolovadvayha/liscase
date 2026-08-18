<?php

use yii\db\Migration;

/**
 * Removes secondary indexes that were verified as redundant on both production databases.
 *
 * Each index was first made INVISIBLE on MySQL 8 and the production statistics cache was
 * rebuilt successfully. The narrower idx_statistics_steam_server index is intentionally
 * retained: hiding it caused a measurable regression in the cache rebuild workload.
 */
class m260819_014500_remove_verified_redundant_indexes extends Migration
{
    /**
     * [table, redundant index, redundant columns, covering index, covering columns]
     */
    private const INDEXES = [
        ['statistics', 'idx_statistics_steam_id', ['steam_id'], 'idx_statistics_steam_id_key', ['steam_id', 'key']],
        ['statistics', 'idx_statistics_server_tag_key', ['server_tag', 'key'], 'idx_statistics_server_tag_key_steam', ['server_tag', 'key', 'steam_id']],
        ['statistics_kills', 'idx_statistics_kills_steam_id_type', ['steam_id', 'type'], 'idx_statistics_kills_steam_type_distance', ['steam_id', 'type', 'distance']],
        ['statistics_kills', 'idx_statistics_kills_server_tag_type', ['server_tag', 'type'], 'idx_statistics_kills_server_tag_type_distance', ['server_tag', 'type', 'distance']],
        ['user_top', 'idx_user_top_user_id', ['user_id'], 'user_id_server_id_key_wipe', ['user_id', 'server_id', 'key', 'wipe']],
        ['user', 'steam_id', ['steam_id'], 'idx-user-steam_id', ['steam_id']],
        ['user_profile', 'user_id', ['user_id'], 'idx_user_profile_user_id', ['user_id']],
    ];

    public function up()
    {
        foreach (self::INDEXES as [$table, $redundant, $columns, $covering, $coveringColumns]) {
            $this->dropIfCovered($table, $redundant, $columns, $covering, $coveringColumns);
        }

        return true;
    }

    public function down()
    {
        foreach (array_reverse(self::INDEXES) as [$table, $index, $columns]) {
            if ($this->indexColumns($table, $index) !== null) {
                continue;
            }

            $this->createIndex($index, $table, $columns);
        }

        return true;
    }

    private function dropIfCovered(
        string $table,
        string $redundant,
        array $expectedColumns,
        string $covering,
        array $expectedCoveringColumns
    ): void {
        $actualColumns = $this->indexColumns($table, $redundant);
        if ($actualColumns === null) {
            echo "Index {$table}.{$redundant} is already absent; skipping.\n";
            return;
        }

        $actualCoveringColumns = $this->indexColumns($table, $covering);
        if ($actualColumns !== $expectedColumns || $actualCoveringColumns !== $expectedCoveringColumns) {
            echo "Index definitions for {$table}.{$redundant} do not match the audited schema; skipping.\n";
            return;
        }

        if (array_slice($actualCoveringColumns, 0, count($actualColumns)) !== $actualColumns) {
            echo "Index {$table}.{$covering} no longer covers {$redundant}; skipping.\n";
            return;
        }

        $quotedTable = $this->db->quoteTableName($table);
        $quotedIndex = $this->db->quoteColumnName($redundant);
        $this->execute("ALTER TABLE {$quotedTable} DROP INDEX {$quotedIndex}");
    }

    private function indexColumns(string $table, string $index): ?array
    {
        $columns = $this->db->createCommand(
            <<<'SQL'
SELECT column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = :table
  AND index_name = :index
ORDER BY seq_in_index
SQL
            ,
            [':table' => $table, ':index' => $index]
        )->queryColumn();

        return $columns === [] ? null : $columns;
    }
}

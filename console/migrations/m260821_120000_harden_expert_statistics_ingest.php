<?php

use console\components\migration\Migration;

/**
 * Idempotency keys for ExpertStatistics delivery and queue retries.
 * Nullable event_id keeps payloads from older plugin versions compatible.
 */
class m260821_120000_harden_expert_statistics_ingest extends Migration
{
    public function safeUp()
    {
        foreach ([
            'statistics_kills' => 'ux-statistics_kills-server-event',
            'servers_chats' => 'ux-servers_chats-server-event',
            'servers_reports' => 'ux-servers_reports-server-event',
        ] as $table => $index) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null) {
                continue;
            }
            if (!isset($schema->columns['event_id'])) {
                $this->addColumn($table, 'event_id', $this->string(64)->null()->after('id'));
            }
            if (!$this->indexExists($table, $index)) {
                $this->createIndex($index, $table, ['server_tag', 'event_id'], true);
            }
        }

        if ($this->db->schema->getTableSchema('plugin_ingest_receipts', true) === null) {
            $this->createTable('plugin_ingest_receipts', [
                'receipt_key' => $this->string(191)->notNull(),
                'server_tag' => $this->string(64)->notNull(),
                'created_at' => $this->integer()->unsigned()->notNull(),
                'PRIMARY KEY ([[receipt_key]])',
            ], self::TABLE_OPTIONS);
            $this->createIndex(
                'idx-plugin_ingest_receipts-created_at',
                'plugin_ingest_receipts',
                'created_at'
            );
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('plugin_ingest_receipts', true) !== null) {
            $this->dropTable('plugin_ingest_receipts');
        }
        foreach ([
            'statistics_kills' => 'ux-statistics_kills-server-event',
            'servers_chats' => 'ux-servers_chats-server-event',
            'servers_reports' => 'ux-servers_reports-server-event',
        ] as $table => $index) {
            $schema = $this->db->schema->getTableSchema($table, true);
            if ($schema === null || !isset($schema->columns['event_id'])) {
                continue;
            }
            if ($this->indexExists($table, $index)) {
                $this->dropIndex($index, $table);
            }
            $this->dropColumn($table, 'event_id');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return array_key_exists($index, $this->db->schema->getTableIndexes($table, true));
    }
}

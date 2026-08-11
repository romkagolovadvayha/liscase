<?php

use console\components\migration\Migration;

/**
 * Adds the composite indexes used by hot API paths and bounded maintenance.
 *
 * This migration intentionally avoids rebuilding the very large statistics
 * tables. Those need online DDL after production slow-query verification.
 */
class m260811_090000_add_runtime_query_indexes extends Migration
{
    public function up()
    {
        $this->createIndexIfMissing(
            'idx_user_tree_parent_created_user',
            'user_tree',
            ['parent_user_id', 'created_at', 'user_id']
        );
        $this->createIndexIfMissing(
            'idx_deposit_user_status',
            'deposit',
            ['user_id', 'status']
        );
        $this->createIndexIfMissing(
            'idx_rcon_tasks_status_created_id',
            'rcon_tasks',
            ['status', 'created_at', 'id']
        );
        $this->createIndexIfMissing(
            'idx_user_confirm_code_status_created_id',
            'user_confirm_code',
            ['status', 'created_at', 'id']
        );

        // This exact duplicate exists on current installations. Keep the
        // older ix_support_read_user_status index and remove the duplicate.
        if ($this->indexExists('support_read', 'idx_support_read_user_status')
            && $this->indexExists('support_read', 'ix_support_read_user_status')) {
            $this->dropIndex('idx_support_read_user_status', 'support_read');
        }
    }

    public function down()
    {
        $this->dropIndexIfPresent('user_confirm_code', 'idx_user_confirm_code_status_created_id');
        $this->dropIndexIfPresent('rcon_tasks', 'idx_rcon_tasks_status_created_id');
        $this->dropIndexIfPresent('deposit', 'idx_deposit_user_status');
        $this->dropIndexIfPresent('user_tree', 'idx_user_tree_parent_created_user');

        if (!$this->indexExists('support_read', 'idx_support_read_user_status')) {
            $this->createIndex('idx_support_read_user_status', 'support_read', ['user_id', 'status']);
        }
    }

    private function createIndexIfMissing(string $name, string $table, array $columns): void
    {
        if (!$this->indexExists($table, $name)) {
            $this->createIndex($name, $table, $columns);
        }
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            $this->dropIndex($name, $table);
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT 1 FROM information_schema.statistics'
            . ' WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :name'
            . ' LIMIT 1',
            [':table' => $table, ':name' => $name]
        )->queryScalar();
    }
}

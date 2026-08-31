<?php

use console\components\migration\Migration;

/**
 * Composite indexes for the paginated profile operation history.
 */
class m260831_120000_add_user_history_indexes extends Migration
{
    public function up()
    {
        $this->createIndexIfMissing(
            'idx_profit_balance_status_created_id',
            'profit',
            ['user_balance_id', 'status', 'created_at', 'id']
        );
        $this->createIndexIfMissing(
            'idx_invoice_user_created_id',
            'invoice',
            ['user_id', 'created_at', 'id']
        );
        $this->createIndexIfMissing(
            'idx_deposit_user_created_id',
            'deposit',
            ['user_id', 'created_at', 'id']
        );
    }

    public function down()
    {
        $this->dropIndexIfPresent('deposit', 'idx_deposit_user_created_id');
        $this->dropIndexIfPresent('invoice', 'idx_invoice_user_created_id');
        $this->dropIndexIfPresent('profit', 'idx_profit_balance_status_created_id');
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

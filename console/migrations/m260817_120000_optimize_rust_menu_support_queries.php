<?php

use console\components\migration\Migration;

/**
 * Adds the covering indexes used by bounded Rust menu API queries.
 *
 * support_message already has an InnoDB secondary index on support_id; its
 * leaf records contain the primary id, so WHERE support_id = ? ORDER BY id DESC
 * does not need another duplicate index.
 */
class m260817_120000_optimize_rust_menu_support_queries extends Migration
{
    private const SUPPORT_INDEX = 'idx_support_user_status_updated_id';
    private const UNREAD_INDEX = 'idx_support_read_user_status_id_support';
    private const OLD_UNREAD_PREFIX_INDEX = 'ix_support_read_user_status';

    public function up()
    {
        $this->createOnlineIndexIfMissing(
            self::SUPPORT_INDEX,
            'support',
            ['user_id', 'status', 'updated_at', 'id']
        );
        $this->createOnlineIndexIfMissing(
            self::UNREAD_INDEX,
            'support_read',
            ['user_id', 'status', 'id', 'support_id']
        );
        // The new covering index has the same (user_id, status) prefix.
        // Dropping the old two-column copy avoids paying for it on every write.
        if ($this->indexExists('support_read', self::UNREAD_INDEX)
            && $this->indexExists('support_read', self::OLD_UNREAD_PREFIX_INDEX)) {
            $this->dropIndex(self::OLD_UNREAD_PREFIX_INDEX, 'support_read');
        }
    }

    public function down()
    {
        if (!$this->indexExists('support_read', self::OLD_UNREAD_PREFIX_INDEX)) {
            $this->createOnlineIndexIfMissing(
                self::OLD_UNREAD_PREFIX_INDEX,
                'support_read',
                ['user_id', 'status']
            );
        }

        $this->dropIndexIfPresent('support_read', self::UNREAD_INDEX);
        $this->dropIndexIfPresent('support', self::SUPPORT_INDEX);
    }

    /**
     * MySQL/MariaDB can build ordinary secondary indexes without blocking
     * reads or writes. Fall back to Yii's portable DDL on older installations.
     */
    private function createOnlineIndexIfMissing(string $name, string $table, array $columns): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $quotedColumns = implode(', ', array_map(static function (string $column): string {
            return '`' . str_replace('`', '``', $column) . '`';
        }, $columns));

        try {
            $this->db->createCommand(
                "ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$quotedColumns}), ALGORITHM=INPLACE, LOCK=NONE"
            )->execute();
        } catch (\Throwable $e) {
            if (!$this->indexExists($table, $name)) {
                $this->createIndex($name, $table, $columns);
            }
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
        return (bool) $this->db->createCommand(
            'SELECT 1 FROM information_schema.statistics'
            . ' WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :name'
            . ' LIMIT 1',
            [':table' => $table, ':name' => $name]
        )->queryScalar();
    }
}

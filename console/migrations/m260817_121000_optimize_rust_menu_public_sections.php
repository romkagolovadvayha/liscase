<?php

use console\components\migration\Migration;

/**
 * Indexes bounded leaderboard-adjacent public sections of the Rust menu.
 */
class m260817_121000_optimize_rust_menu_public_sections extends Migration
{
    private const INDEXES = [
        'idx_clans_server_experience_id' => [
            'table' => 'clans',
            'columns' => ['server_id', 'experience', 'id'],
        ],
        'idx_clan_members_clan_leave' => [
            'table' => 'clan_members',
            'columns' => ['clan_id', 'leave_date'],
        ],
        'idx_wipe_calendar_server_at_id' => [
            'table' => 'wipe_calendar_event',
            'columns' => ['server_id', 'event_at', 'id'],
        ],
        'idx_wipe_calendar_server_type_at_id' => [
            'table' => 'wipe_calendar_event',
            'columns' => ['server_id', 'event_type', 'event_at', 'id'],
        ],
    ];

    public function up()
    {
        foreach (self::INDEXES as $name => $definition) {
            $this->createOnlineIndexIfMissing($name, $definition['table'], $definition['columns']);
        }
    }

    public function down()
    {
        foreach (array_reverse(self::INDEXES, true) as $name => $definition) {
            if ($this->indexExists($definition['table'], $name)) {
                $this->dropIndex($name, $definition['table']);
            }
        }
    }

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

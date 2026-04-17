<?php

use console\components\migration\Migration;

/**
 * Уникальный индекс по user.socket_room для {@see \common\models\user\User::findBySocketRoom()}
 * и коллизий при {@see \common\models\user\User::generateSocketRoom()}.
 */
class m260417_140000_user_socket_room_index extends Migration
{
    private const INDEX_NAME = 'idx_user_socket_room';

    public function safeUp()
    {
        $schema = $this->db->getTableSchema('{{%user}}', true);
        if ($schema === null || $schema->getColumn('socket_room') === null) {
            return;
        }

        $this->createIndexIfNotExists(self::INDEX_NAME, '{{%user}}', 'socket_room', true);
    }

    public function safeDown()
    {
        $this->dropIndexIfExists(self::INDEX_NAME, '{{%user}}');
    }

    /**
     * @param string|string[] $columns
     */
    protected function createIndexIfNotExists(string $name, string $table, $columns, bool $unique = false): void
    {
        $rawTable = $this->resolveRawTableName($table);
        if ($rawTable === null) {
            return;
        }

        $quotedTable = '`' . str_replace('`', '``', $rawTable) . '`';
        $indexExists = $this->db->createCommand(
            "SHOW INDEX FROM {$quotedTable} WHERE Key_name = :name",
            [':name' => $name]
        )->queryOne();

        if (!$indexExists) {
            $this->createIndex($name, $table, $columns, $unique);
            echo "  > Created index '{$name}' on '{$rawTable}'\n";
        } else {
            echo "  > Index '{$name}' already exists on '{$rawTable}'\n";
        }
    }

    protected function dropIndexIfExists(string $name, string $table): void
    {
        $rawTable = $this->resolveRawTableName($table);
        if ($rawTable === null) {
            return;
        }

        $quotedTable = '`' . str_replace('`', '``', $rawTable) . '`';
        $indexExists = $this->db->createCommand(
            "SHOW INDEX FROM {$quotedTable} WHERE Key_name = :name",
            [':name' => $name]
        )->queryOne();

        if ($indexExists) {
            $this->dropIndex($name, $table);
            echo "  > Dropped index '{$name}' on '{$rawTable}'\n";
        }
    }

    private function resolveRawTableName(string $table): ?string
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null ? $schema->name : null;
    }
}

<?php

use console\components\migration\Migration as BaseMigration;

/**
 * Baseline статистики на момент вступления в клан (дельта от общей statistics за вайп).
 * Статус участника в разрезе вайпа: active / former (заморозка при выходе).
 *
 * Идемпотентность: при повторном запуске после частичного применения (таблица есть, запись в migration нет)
 * не падаем на CREATE TABLE / дублях индексов и FK.
 *
 * Типы INT как в m260127: `clan_members.id` = INT(10) UNSIGNED (см. Migration::PRIMARY_KEY / INT_FIELD_NOT_NULL).
 * Yii `integer()->unsigned()` даёт int(11) — в части окружений InnoDB даёт errno 150 на FK к INT(10) UNSIGNED.
 */
class m260321_120000_clan_member_stats_baseline_and_status extends BaseMigration
{
    private const BASELINE_TABLE = 'clan_member_stats_baseline';
    private const STATS_TABLE = 'clan_member_statistics';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::BASELINE_TABLE, true) === null) {
            $this->createTable(self::BASELINE_TABLE, [
                'id' => self::PRIMARY_KEY,
                'clan_member_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Участник клана\'',
                'server_id' => self::INT_FIELD_NOT_NULL,
                'wipe' => $this->string(255)->notNull()->comment('Ключ вайпа как в statistics'),
                'stat_key' => $this->string(64)->notNull()->comment('Ключ метрики как в statistics.key'),
                'value' => $this->bigInteger()->notNull()->defaultValue(0)->comment('Значение statistics на момент фиксации baseline'),
                'created_at' => 'INT(10) UNSIGNED NOT NULL',
            ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        $this->alignBaselineIntColumnsForForeignKeys();

        if (!$this->indexExists(self::BASELINE_TABLE, 'idx-clan_member_stats_baseline-unique')) {
            $this->createIndex(
                'idx-clan_member_stats_baseline-unique',
                self::BASELINE_TABLE,
                ['clan_member_id', 'server_id', 'wipe', 'stat_key'],
                true
            );
        }
        if (!$this->indexExists(self::BASELINE_TABLE, 'idx-clan_member_stats_baseline-member')) {
            $this->createIndex('idx-clan_member_stats_baseline-member', self::BASELINE_TABLE, 'clan_member_id');
        }

        if (!$this->foreignKeyExists(self::BASELINE_TABLE, 'fk-clan_member_stats_baseline-member')) {
            $this->addForeignKey(
                'fk-clan_member_stats_baseline-member',
                self::BASELINE_TABLE,
                'clan_member_id',
                'clan_members',
                'id',
                'CASCADE'
            );
        }

        $statsSchema = $this->db->schema->getTableSchema(self::STATS_TABLE, true);
        if ($statsSchema !== null) {
            if (!isset($statsSchema->columns['member_status'])) {
                $this->addColumn(self::STATS_TABLE, 'member_status', "VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active|former'");
            }
            if (!isset($statsSchema->columns['frozen_at'])) {
                $this->addColumn(self::STATS_TABLE, 'frozen_at', $this->integer()->unsigned()->null()->comment('Время заморозки строки (бывший участник за вайп)'));
            }
            $this->db->schema->refreshTableSchema(self::STATS_TABLE);
            if (!$this->indexExists(self::STATS_TABLE, 'idx-clan_member_statistics-member_status')) {
                $this->createIndex('idx-clan_member_statistics-member_status', self::STATS_TABLE, 'member_status');
            }
        }
    }

    public function safeDown()
    {
        if ($this->foreignKeyExists(self::BASELINE_TABLE, 'fk-clan_member_stats_baseline-member')) {
            $this->dropForeignKey('fk-clan_member_stats_baseline-member', self::BASELINE_TABLE);
        }
        if ($this->db->schema->getTableSchema(self::BASELINE_TABLE, true) !== null) {
            $this->dropTable(self::BASELINE_TABLE);
        }

        $statsSchema = $this->db->schema->getTableSchema(self::STATS_TABLE, true);
        if ($statsSchema !== null) {
            if (isset($statsSchema->columns['frozen_at'])) {
                $this->dropColumn(self::STATS_TABLE, 'frozen_at');
            }
            if (isset($statsSchema->columns['member_status'])) {
                $this->dropColumn(self::STATS_TABLE, 'member_status');
            }
        }
    }

    /**
     * InnoDB errno 150: тип дочерней колонки должен совпадать с родительской (в т.ч. INT(10) vs int(11), signed/unsigned).
     */
    private function alignBaselineIntColumnsForForeignKeys(): void
    {
        if ($this->db->schema->getTableSchema(self::BASELINE_TABLE, true) === null) {
            return;
        }

        $memberIdRef = $this->db->schema->getTableSchema('{{%clan_members}}', true);
        $memberIdCol = $memberIdRef !== null ? $memberIdRef->getColumn('id') : null;
        if ($memberIdCol !== null) {
            $this->alterColumn(
                self::BASELINE_TABLE,
                'clan_member_id',
                $memberIdCol->dbType . ' NOT NULL COMMENT \'Участник клана\''
            );
        }

        $serversSchema = $this->db->schema->getTableSchema('{{%servers}}', true);
        $serverIdCol = $serversSchema !== null ? $serversSchema->getColumn('id') : null;
        if ($serverIdCol !== null) {
            $this->alterColumn(self::BASELINE_TABLE, 'server_id', $serverIdCol->dbType . ' NOT NULL');
        }

        $this->db->schema->refreshTableSchema(self::BASELINE_TABLE);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $sql = 'SELECT COUNT(*) FROM information_schema.statistics
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :name';
        return (int) $this->db->createCommand($sql, [
            ':db' => $dbName,
            ':table' => $table,
            ':name' => $indexName,
        ])->queryScalar() > 0;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = :db AND TABLE_NAME = :table AND CONSTRAINT_NAME = :name AND CONSTRAINT_TYPE = :type';
        return (int) $this->db->createCommand($sql, [
            ':db' => $dbName,
            ':table' => $table,
            ':name' => $constraintName,
            ':type' => 'FOREIGN KEY',
        ])->queryScalar() > 0;
    }
}

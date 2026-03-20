<?php

use console\components\migration\Migration as BaseMigration;
use yii\db\Query;

/**
 * Статистика клана: key/value вместо десятков колонок в clan_statistics.
 * Заголовок: clan_statistics (clan_id, server_id, wipe, …).
 * Значения: clan_statistics_values (clan_statistics_id, stat_key, value).
 */
class m260322_150000_clan_statistics_key_value extends BaseMigration
{
    private const VALUES_TABLE = 'clan_statistics_values';
    private const HEADER_TABLE = 'clan_statistics';

    /** Колонки заголовка (остаются в clan_statistics). */
    private const HEADER_COLUMNS = ['id', 'clan_id', 'server_id', 'wipe', 'updated_at', 'last_activity_date'];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::VALUES_TABLE, true) === null) {
            $this->createTable(self::VALUES_TABLE, [
                'id' => $this->primaryKey(),
                'clan_statistics_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Запись clan_statistics\'',
                'stat_key' => "VARCHAR(80) NOT NULL COMMENT 'Ключ метрики (total_*, top_*, …)'",
                'value' => 'DECIMAL(24, 6) NOT NULL DEFAULT 0 COMMENT \'Значение (целое или дробное для топов)\'',
            ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->createIndex(
                'idx-clan_statistics_values-unique',
                self::VALUES_TABLE,
                ['clan_statistics_id', 'stat_key'],
                true
            );
            $this->createIndex('idx-clan_statistics_values-key', self::VALUES_TABLE, 'stat_key');

            $this->addForeignKey(
                'fk-clan_statistics_values-header',
                self::VALUES_TABLE,
                'clan_statistics_id',
                self::HEADER_TABLE,
                'id',
                'CASCADE'
            );
        }

        $schema = $this->db->schema->getTableSchema(self::HEADER_TABLE, true);
        if ($schema === null) {
            return;
        }

        $valueColumnNames = [];
        foreach ($schema->columns as $name => $_col) {
            if (!in_array($name, self::HEADER_COLUMNS, true)) {
                $valueColumnNames[] = $name;
            }
        }

        if ($valueColumnNames === []) {
            return;
        }

        $rows = (new Query())->from(self::HEADER_TABLE)->all();
        foreach ($rows as $row) {
            $cid = (int)$row['id'];
            foreach ($valueColumnNames as $col) {
                if (!array_key_exists($col, $row)) {
                    continue;
                }
                $v = $row[$col];
                if ($v === null || $v === '') {
                    continue;
                }
                $exists = (new Query())
                    ->from(self::VALUES_TABLE)
                    ->where(['clan_statistics_id' => $cid, 'stat_key' => $col])
                    ->exists();
                if ($exists) {
                    continue;
                }
                $this->insert(self::VALUES_TABLE, [
                    'clan_statistics_id' => $cid,
                    'stat_key' => $col,
                    'value' => $v,
                ]);
            }
        }

        $drops = [];
        foreach ($valueColumnNames as $col) {
            $drops[] = 'DROP COLUMN ' . $this->db->quoteColumnName($col);
        }
        if ($drops !== []) {
            $this->execute('ALTER TABLE ' . $this->db->quoteTableName(self::HEADER_TABLE) . ' ' . implode(', ', $drops));
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema(self::HEADER_TABLE, true);
        if ($schema === null) {
            return;
        }

        $statCols = [
            'total_kills' => 'INT(10) UNSIGNED DEFAULT 0',
            'total_deaths' => 'INT(10) UNSIGNED DEFAULT 0',
            'top_reider' => 'DECIMAL(10,2) DEFAULT 0',
            'top_kills' => 'DECIMAL(10,2) DEFAULT 0',
        ];
        foreach ($statCols as $col => $type) {
            if (!isset($schema->columns[$col])) {
                $this->addColumn(self::HEADER_TABLE, $col, $type);
            }
        }

        $pairs = (new Query())
            ->select(['clan_statistics_id', 'stat_key', 'value'])
            ->from(self::VALUES_TABLE)
            ->all();

        foreach ($pairs as $p) {
            $key = $p['stat_key'];
            if (!isset($statCols[$key])) {
                continue;
            }
            $this->update(
                self::HEADER_TABLE,
                [$key => $p['value']],
                ['id' => $p['clan_statistics_id']]
            );
        }

        if ($this->db->schema->getTableSchema(self::VALUES_TABLE, true) !== null) {
            $this->dropForeignKey('fk-clan_statistics_values-header', self::VALUES_TABLE);
            $this->dropTable(self::VALUES_TABLE);
        }
    }
}

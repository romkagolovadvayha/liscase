<?php

use console\components\migration\Migration as BaseMigration;

/**
 * Метрики участника за вайп — в EAV (clan_member_statistics_values), заголовок в clan_member_statistics.
 */
class m260322_150000_clan_member_statistics_values extends BaseMigration
{
    private const HEADER_TABLE = 'clan_member_statistics';
    private const VALUES_TABLE = 'clan_member_statistics_values';

    /** Колонки со старым «плоским» хранением (всё переносим в values, затем дропаем). */
    private const LEGACY_STAT_COLUMNS = [
        'kills', 'deaths', 'scientists', 'wounded', 'tcs_destroyed', 'nude_kills',
        'hits_head', 'hits_neck', 'hits_chest', 'hits_lowerspine',
        'hits_lefthand', 'hits_leftleg', 'hits_leftfoot',
        'hits_righthand', 'hits_rightleg', 'hits_rightfoot',
        'c4thrown', 'satchelsthrown', 'rocket_basic', 'rocket_hv', 'rocket_fire',
        'ammo_explosive', 'grenade_f1_deployed', 'grenade_molotov_deployed', 'grenade_beancan_deployed',
        'wood', 'stones', 'metal_ore', 'sulfur_ore',
        'f_fish_anchovy', 'f_fish_catfish', 'f_fish_herring', 'f_fish_orangeroughy',
        'f_fish_salmon', 'f_fish_sardine', 'f_fish_smallshark', 'f_fish_troutsmall', 'f_fish_yellowperch',
        'chicken', 'bear', 'boar', 'polarbear', 'stag', 'horse',
        'wolf2', 'wolf', 'simpleshark', 'panther', 'crocodile', 'tiger',
        'gathered_cloth', 'gathered_pumpkin', 'gathered_corn', 'gathered_green_berry',
        'gathered_blue_berry', 'gathered_yellow_berry', 'gathered_red_berry', 'gathered_white_berry',
        'gathered_black_berry', 'gathered_potato', 'gathered_orchid', 'gathered_rose',
        'gathered_sunflower', 'gathered_wheat',
        'playtime', 'crate_open', 'barrel', 'helicopters', 'bradleys',
        'research_table_looted', 'excavator_mined', 'raids_completed', 'raids_defended',
        'top_reider', 'top_kills', 'top_scientists', 'top_playtime', 'top_farmer',
        'top_fishing', 'top_hunter', 'top_fermer',
    ];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::HEADER_TABLE, true) === null) {
            return;
        }

        if ($this->db->schema->getTableSchema(self::VALUES_TABLE, true) === null) {
            $this->createTable(self::VALUES_TABLE, [
                'id' => self::PRIMARY_KEY,
                'clan_member_statistics_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'Запись clan_member_statistics\'',
                'stat_key' => "VARCHAR(80) NOT NULL COMMENT 'Ключ метрики (kills, top_kills, …)'",
                'value' => 'DECIMAL(24, 6) NOT NULL DEFAULT 0 COMMENT \'Значение\'',
            ], self::TABLE_OPTIONS);

            $this->createIndex(
                'idx-clan_member_statistics_values-unique',
                self::VALUES_TABLE,
                ['clan_member_statistics_id', 'stat_key'],
                true
            );
            $this->createIndex('idx-clan_member_statistics_values-key', self::VALUES_TABLE, 'stat_key');

            $this->addForeignKey(
                'fk-clan_member_statistics_values-header',
                self::VALUES_TABLE,
                'clan_member_statistics_id',
                self::HEADER_TABLE,
                'id',
                'CASCADE'
            );
        }

        $headerSchema = $this->db->schema->getTableSchema(self::HEADER_TABLE, true);
        if ($headerSchema === null || !isset($headerSchema->columns['kills'])) {
            return;
        }

        $db = $this->db;
        foreach (self::LEGACY_STAT_COLUMNS as $col) {
            if (!isset($headerSchema->columns[$col])) {
                continue;
            }
            $qCol = $db->quoteColumnName($col);
            $qKey = $db->quoteValue($col);
            $sql = 'INSERT INTO ' . $db->quoteTableName(self::VALUES_TABLE)
                . ' (`clan_member_statistics_id`, `stat_key`, `value`)'
                . ' SELECT `id`, ' . $qKey . ', ' . $qCol . ' FROM ' . $db->quoteTableName(self::HEADER_TABLE);
            $this->execute($sql);
        }

        foreach (self::LEGACY_STAT_COLUMNS as $col) {
            $headerSchema = $this->db->schema->getTableSchema(self::HEADER_TABLE, true);
            if ($headerSchema !== null && isset($headerSchema->columns[$col])) {
                $this->dropColumn(self::HEADER_TABLE, $col);
            }
        }

        $this->db->schema->refreshTableSchema(self::HEADER_TABLE);
        $this->db->schema->refreshTableSchema(self::VALUES_TABLE);
    }

    public function safeDown()
    {
        $headerSchema = $this->db->schema->getTableSchema(self::HEADER_TABLE, true);
        if ($headerSchema === null) {
            return;
        }

        foreach (self::LEGACY_STAT_COLUMNS as $col) {
            if (!isset($headerSchema->columns[$col])) {
                $type = strpos((string)$col, 'top_') === 0
                    ? 'DECIMAL(10,2) NOT NULL DEFAULT 0'
                    : 'INT(10) UNSIGNED NOT NULL DEFAULT 0';
                $this->addColumn(self::HEADER_TABLE, $col, $type);
            }
        }

        $this->db->schema->refreshTableSchema(self::HEADER_TABLE);

        if ($this->db->schema->getTableSchema(self::VALUES_TABLE, true) !== null) {
            $rows = (new \yii\db\Query())
                ->from(self::VALUES_TABLE)
                ->all($this->db);
            foreach ($rows as $row) {
                $hid = (int)$row['clan_member_statistics_id'];
                $key = (string)$row['stat_key'];
                if (!in_array($key, self::LEGACY_STAT_COLUMNS, true)) {
                    continue;
                }
                $this->update(
                    self::HEADER_TABLE,
                    [$key => $row['value']],
                    ['id' => $hid]
                );
            }

            if ($this->foreignKeyExists(self::VALUES_TABLE, 'fk-clan_member_statistics_values-header')) {
                $this->dropForeignKey('fk-clan_member_statistics_values-header', self::VALUES_TABLE);
            }
            $this->dropTable(self::VALUES_TABLE);
        }
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

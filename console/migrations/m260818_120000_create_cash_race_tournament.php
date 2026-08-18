<?php

use console\components\migration\Migration;
use yii\db\ColumnSchema;
use yii\db\Schema;

/**
 * Adds the private-preview Cash Race tournament mode.
 *
 * Physical keys are represented by immutable token rows. This makes deposits
 * auditable and prevents keys from being transferred or submitted twice.
 */
class m260818_120000_create_cash_race_tournament extends Migration
{
    public function safeUp()
    {
        $userColumn = $this->db->schema->getTableSchema('{{%user}}')->getColumn('id');
        $serverColumn = $this->db->schema->getTableSchema('{{%servers}}')->getColumn('id');
        $userId = $this->columnType($userColumn);
        $serverId = $this->columnType($serverColumn);

        $this->addColumn('tournaments', 'type', "VARCHAR(24) NOT NULL DEFAULT 'clan' AFTER `id`");
        $this->createIndex('idx-tournaments-type-status-dates', 'tournaments', ['type', 'status', 'starts_at', 'ends_at']);

        $this->createTable('cash_race_tournament', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'preview_only' => "TINYINT(1) NOT NULL DEFAULT 1",
            'preview_steam_id' => "VARCHAR(32) NOT NULL DEFAULT '76561198394504608'",
            'drop_chance' => "DECIMAL(6,5) NOT NULL DEFAULT 0.12000",
            'drop_min' => "TINYINT UNSIGNED NOT NULL DEFAULT 1",
            'drop_max' => "TINYINT UNSIGNED NOT NULL DEFAULT 2",
            'key_shortname' => "VARCHAR(64) NOT NULL DEFAULT 'keycard_green'",
            'key_skin_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'terminal_active_seconds' => "INT UNSIGNED NOT NULL DEFAULT 1200",
            'terminal_cooldown_min_seconds' => "INT UNSIGNED NOT NULL DEFAULT 1200",
            'terminal_cooldown_max_seconds' => "INT UNSIGNED NOT NULL DEFAULT 1200",
            'terminal_prefab' => "VARCHAR(255) NOT NULL DEFAULT 'assets/prefabs/deployable/vendingmachine/vendingmachine.deployed.prefab'",
            'allowed_monuments' => 'JSON DEFAULT NULL',
            'gold_medal_id' => self::INT_FIELD,
            'silver_medal_id' => self::INT_FIELD,
            'bronze_medal_id' => self::INT_FIELD,
            'finished_at' => 'DATETIME DEFAULT NULL',
            'awards_issued_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'INT UNSIGNED NOT NULL',
            'updated_at' => 'INT UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);
        $this->createIndex('uq-cash-race-tournament', 'cash_race_tournament', 'tournament_id', true);
        $this->addForeignKey('fk-cash-race-tournament-master', 'cash_race_tournament', 'tournament_id', 'tournaments', 'id', 'CASCADE');
        foreach (['gold', 'silver', 'bronze'] as $color) {
            $this->addForeignKey("fk-cash-race-{$color}-medal", 'cash_race_tournament', "{$color}_medal_id", 'medal', 'id', 'SET NULL');
        }

        $this->createTable('cash_race_score', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'user_id' => $userId->notNull(),
            'steam_id' => 'VARCHAR(32) NOT NULL',
            'keys_found' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'keys_lost' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'keys_deposited' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'position' => 'INT UNSIGNED DEFAULT NULL',
            'last_found_at' => 'DATETIME DEFAULT NULL',
            'last_deposited_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'INT UNSIGNED NOT NULL',
            'updated_at' => 'INT UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);
        $this->createIndex('uq-cash-race-score-user', 'cash_race_score', ['tournament_id', 'user_id'], true);
        $this->createIndex('idx-cash-race-score-ranking', 'cash_race_score', ['tournament_id', 'keys_deposited', 'last_deposited_at']);
        $this->createIndex('idx-cash-race-score-steam', 'cash_race_score', ['tournament_id', 'steam_id']);
        $this->addForeignKey('fk-cash-race-score-tournament', 'cash_race_score', 'tournament_id', 'tournaments', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-score-user', 'cash_race_score', 'user_id', 'user', 'id', 'CASCADE');

        $this->createTable('cash_race_terminal_session', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'server_id' => $serverId->notNull(),
            'session_uuid' => 'CHAR(36) NOT NULL',
            'monument_key' => 'VARCHAR(128) NOT NULL',
            'monument_name' => 'VARCHAR(255) NOT NULL',
            'position_json' => 'VARCHAR(255) DEFAULT NULL',
            'spawned_at' => 'DATETIME NOT NULL',
            'expires_at' => 'DATETIME NOT NULL',
            'closed_at' => 'DATETIME DEFAULT NULL',
            'status' => "ENUM('active','expired','destroyed') NOT NULL DEFAULT 'active'",
            'created_at' => 'INT UNSIGNED NOT NULL',
            'updated_at' => 'INT UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);
        $this->createIndex('uq-cash-race-terminal-uuid', 'cash_race_terminal_session', 'session_uuid', true);
        $this->createIndex('idx-cash-race-terminal-active', 'cash_race_terminal_session', ['tournament_id', 'server_id', 'status', 'expires_at']);
        $this->addForeignKey('fk-cash-race-terminal-tournament', 'cash_race_terminal_session', 'tournament_id', 'tournaments', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-terminal-server', 'cash_race_terminal_session', 'server_id', 'servers', 'id', 'CASCADE');

        $this->createTable('cash_race_key_token', [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'token_uuid' => 'CHAR(36) NOT NULL',
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'server_id' => $serverId->notNull(),
            'user_id' => $userId->notNull(),
            'steam_id' => 'VARCHAR(32) NOT NULL',
            'state' => "ENUM('held','lost','deposited') NOT NULL DEFAULT 'held'",
            'terminal_session_id' => self::INT_FIELD,
            'issued_at' => 'DATETIME NOT NULL',
            'consumed_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'INT UNSIGNED NOT NULL',
            'updated_at' => 'INT UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);
        $this->createIndex('uq-cash-race-token-uuid', 'cash_race_key_token', 'token_uuid', true);
        $this->createIndex('idx-cash-race-token-owner-state', 'cash_race_key_token', ['tournament_id', 'steam_id', 'state']);
        $this->addForeignKey('fk-cash-race-token-tournament', 'cash_race_key_token', 'tournament_id', 'tournaments', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-token-server', 'cash_race_key_token', 'server_id', 'servers', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-token-user', 'cash_race_key_token', 'user_id', 'user', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-token-terminal', 'cash_race_key_token', 'terminal_session_id', 'cash_race_terminal_session', 'id', 'SET NULL');

        $this->createTable('cash_race_deposit', [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'deposit_uuid' => 'CHAR(36) NOT NULL',
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'terminal_session_id' => self::INT_FIELD_NOT_NULL,
            'server_id' => $serverId->notNull(),
            'user_id' => $userId->notNull(),
            'steam_id' => 'VARCHAR(32) NOT NULL',
            'keys_count' => 'INT UNSIGNED NOT NULL',
            'created_at' => 'INT UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);
        $this->createIndex('uq-cash-race-deposit-uuid', 'cash_race_deposit', 'deposit_uuid', true);
        $this->createIndex('idx-cash-race-deposit-board', 'cash_race_deposit', ['tournament_id', 'keys_count', 'created_at']);
        $this->addForeignKey('fk-cash-race-deposit-tournament', 'cash_race_deposit', 'tournament_id', 'tournaments', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-deposit-terminal', 'cash_race_deposit', 'terminal_session_id', 'cash_race_terminal_session', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-deposit-server', 'cash_race_deposit', 'server_id', 'servers', 'id', 'CASCADE');
        $this->addForeignKey('fk-cash-race-deposit-user', 'cash_race_deposit', 'user_id', 'user', 'id', 'CASCADE');

        $now = time();
        $medals = [
            ['cash-race-gold', 'Звезда денежной гонки — золото', '1 место в турнире «Денежная гонка».', '/images/cash-race/medal-gold.webp'],
            ['cash-race-silver', 'Звезда денежной гонки — серебро', '2 место в турнире «Денежная гонка».', '/images/cash-race/medal-silver.webp'],
            ['cash-race-bronze', 'Звезда денежной гонки — бронза', '3 место в турнире «Денежная гонка».', '/images/cash-race/medal-bronze.webp'],
        ];
        foreach ($medals as $medal) {
            $this->insert('medal', [
                'code' => $medal[0], 'name' => $medal[1], 'description' => $medal[2],
                'image_path' => $medal[3], 'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s', $now), 'updated_at' => date('Y-m-d H:i:s', $now),
            ]);
        }
    }

    public function safeDown()
    {
        foreach (['cash_race_deposit', 'cash_race_key_token', 'cash_race_terminal_session', 'cash_race_score', 'cash_race_tournament'] as $table) {
            $this->dropTable($table);
        }
        $this->delete('medal', ['code' => ['cash-race-gold', 'cash-race-silver', 'cash-race-bronze']]);
        $this->dropIndex('idx-tournaments-type-status-dates', 'tournaments');
        $this->dropColumn('tournaments', 'type');
    }

    private function columnType(?ColumnSchema $column)
    {
        if ($column === null) return $this->integer();
        $builder = $column->type === Schema::TYPE_BIGINT ? $this->bigInteger() : $this->integer();
        if ($column->unsigned) $builder->unsigned();
        return $builder;
    }
}

<?php

use console\components\migration\Migration;
use yii\db\ColumnSchema;
use yii\db\Schema;

/**
 * Турниры кланов: сущности, регистрации, состав, рейтинг, награды.
 */
class m260604_120000_create_tournament_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $serversIdColumn = $this->db->schema->getTableSchema('{{%servers}}')->getColumn('id');
        $userIdColumn = $this->db->schema->getTableSchema('{{%user}}')->getColumn('id');
        $clanIdColumn = $this->db->schema->getTableSchema('{{%clans}}')->getColumn('id');

        $serverIdType = $this->resolveColumnType($serversIdColumn);
        $userIdType = $this->resolveColumnType($userIdColumn);
        $clanIdType = $this->resolveColumnType($clanIdColumn);

        $this->createTable('tournaments', [
            'id' => self::PRIMARY_KEY,
            'slug' => 'VARCHAR(128) NOT NULL COMMENT \'ЧПУ для URL\'',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT DEFAULT NULL',
            'server_id' => $serverIdType->notNull(),
            'status' => "ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'",
            'starts_at' => 'DATETIME NOT NULL',
            'ends_at' => 'DATETIME NOT NULL',
            'registration_ends_at' => 'DATETIME DEFAULT NULL',
            'max_clans' => 'INT(10) UNSIGNED DEFAULT NULL',
            'max_participants_per_clan' => 'INT(10) UNSIGNED DEFAULT NULL',
            'prize_pool_label' => 'VARCHAR(255) DEFAULT NULL',
            'cover_image' => 'VARCHAR(512) DEFAULT NULL',
            'format_label' => 'VARCHAR(128) DEFAULT NULL',
            'tags' => 'JSON DEFAULT NULL',
            'rules_text' => 'MEDIUMTEXT DEFAULT NULL',
            'sort' => 'INT(10) NOT NULL DEFAULT 0',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-tournaments-slug', 'tournaments', 'slug', true);
        $this->createIndex('idx-tournaments-server_id', 'tournaments', 'server_id');
        $this->createIndex('idx-tournaments-status', 'tournaments', 'status');
        $this->createIndex('idx-tournaments-starts_at', 'tournaments', 'starts_at');
        $this->createIndex('idx-tournaments-ends_at', 'tournaments', 'ends_at');

        $this->addForeignKey(
            'fk-tournaments-server_id',
            'tournaments',
            'server_id',
            'servers',
            'id',
            'CASCADE'
        );

        $this->createTable('tournament_rewards', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'place' => 'TINYINT(3) UNSIGNED NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'subtitle' => 'VARCHAR(255) DEFAULT NULL',
            'image' => 'VARCHAR(512) DEFAULT NULL',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-tournament_rewards-tournament_place', 'tournament_rewards', ['tournament_id', 'place'], true);
        $this->addForeignKey(
            'fk-tournament_rewards-tournament_id',
            'tournament_rewards',
            'tournament_id',
            'tournaments',
            'id',
            'CASCADE'
        );

        $this->createTable('tournament_registrations', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'clan_id' => $clanIdType->notNull(),
            'registered_by_user_id' => $userIdType->notNull(),
            'registered_at' => 'DATETIME NOT NULL',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-tournament_registrations-tournament_clan', 'tournament_registrations', ['tournament_id', 'clan_id'], true);
        $this->createIndex('idx-tournament_registrations-clan_id', 'tournament_registrations', 'clan_id');
        $this->addForeignKey(
            'fk-tournament_registrations-tournament_id',
            'tournament_registrations',
            'tournament_id',
            'tournaments',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-tournament_registrations-clan_id',
            'tournament_registrations',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-tournament_registrations-user_id',
            'tournament_registrations',
            'registered_by_user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->createTable('tournament_participants', [
            'id' => self::PRIMARY_KEY,
            'registration_id' => self::INT_FIELD_NOT_NULL,
            'user_id' => $userIdType->notNull(),
            'added_at' => 'DATETIME NOT NULL',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-tournament_participants-reg_user', 'tournament_participants', ['registration_id', 'user_id'], true);
        $this->createIndex('idx-tournament_participants-user_id', 'tournament_participants', 'user_id');
        $this->addForeignKey(
            'fk-tournament_participants-registration_id',
            'tournament_participants',
            'registration_id',
            'tournament_registrations',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-tournament_participants-user_id',
            'tournament_participants',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->createTable('tournament_rankings', [
            'id' => self::PRIMARY_KEY,
            'tournament_id' => self::INT_FIELD_NOT_NULL,
            'clan_id' => $clanIdType->notNull(),
            'score' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'position' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'calculated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-tournament_rankings-tournament_clan', 'tournament_rankings', ['tournament_id', 'clan_id'], true);
        $this->createIndex('idx-tournament_rankings-tournament_position', 'tournament_rankings', ['tournament_id', 'position']);
        $this->addForeignKey(
            'fk-tournament_rankings-tournament_id',
            'tournament_rankings',
            'tournament_id',
            'tournaments',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-tournament_rankings-clan_id',
            'tournament_rankings',
            'clan_id',
            'clans',
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('tournament_rankings');
        $this->dropTable('tournament_participants');
        $this->dropTable('tournament_registrations');
        $this->dropTable('tournament_rewards');
        $this->dropTable('tournaments');
    }

    /**
     * @param ColumnSchema|null $column
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function resolveColumnType(?ColumnSchema $column)
    {
        if ($column === null) {
            return $this->integer();
        }

        switch ($column->type) {
            case Schema::TYPE_BIGINT:
                $builder = $this->bigInteger();
                break;
            case Schema::TYPE_SMALLINT:
                $builder = $this->smallInteger();
                break;
            case Schema::TYPE_TINYINT:
                $builder = $this->tinyInteger();
                break;
            default:
                $builder = $this->integer();
        }

        if ($column->unsigned) {
            $builder->unsigned();
        }

        return $builder;
    }
}

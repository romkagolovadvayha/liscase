<?php

use console\components\migration\Migration;

/**
 * Adds seasonal battle passes and a standalone user medal system.
 */
class m260813_220000_create_battle_pass_and_medals extends Migration
{
    public function safeUp()
    {
        $this->createTable('medal', [
            'id' => self::PRIMARY_KEY,
            'name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT DEFAULT NULL',
            'image_path' => 'VARCHAR(512) DEFAULT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-medal-is_active', 'medal', 'is_active');

        $this->createTable('user_medal', [
            'id' => self::PRIMARY_KEY,
            'user_id' => self::INT_FIELD_NOT_NULL,
            'medal_id' => self::INT_FIELD_NOT_NULL,
            'source_type' => "VARCHAR(32) NOT NULL DEFAULT 'manual'",
            'source_id' => self::INT_FIELD,
            'note' => 'TEXT DEFAULT NULL',
            'awarded_by_user_id' => self::INT_FIELD,
            'awarded_at' => 'DATETIME NOT NULL',
            'created_at' => 'DATETIME NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-user_medal-user_medal', 'user_medal', ['user_id', 'medal_id'], true);
        $this->createIndex('idx-user_medal-source', 'user_medal', ['source_type', 'source_id']);
        $this->createIndex('idx-user_medal-awarded_at', 'user_medal', 'awarded_at');
        $this->addForeignKey('fk-user_medal-user_id', 'user_medal', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-user_medal-medal_id', 'user_medal', 'medal_id', 'medal', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-user_medal-awarded_by_user_id', 'user_medal', 'awarded_by_user_id', 'user', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('battle_pass_season', [
            'id' => self::PRIMARY_KEY,
            'name' => 'VARCHAR(255) NOT NULL',
            'slug' => 'VARCHAR(128) NOT NULL',
            'season_number' => 'INT(10) UNSIGNED NOT NULL',
            'description' => 'TEXT DEFAULT NULL',
            'starts_at' => 'DATETIME NOT NULL',
            'ends_at' => 'DATETIME DEFAULT NULL',
            'status' => "ENUM('draft','active','finished') NOT NULL DEFAULT 'draft'",
            'reward_type' => "ENUM('item','currency') NOT NULL DEFAULT 'item'",
            'reward_item_id' => self::INT_FIELD,
            'reward_currency' => "VARCHAR(50) DEFAULT 'personal'",
            'reward_amount' => 'DECIMAL(18,2) DEFAULT NULL',
            'medal_id' => self::INT_FIELD,
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-battle_pass_season-slug', 'battle_pass_season', 'slug', true);
        $this->createIndex('idx-battle_pass_season-status_dates', 'battle_pass_season', ['status', 'starts_at', 'ends_at']);
        $this->addForeignKey('fk-battle_pass_season-reward_item_id', 'battle_pass_season', 'reward_item_id', 'drop', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-battle_pass_season-medal_id', 'battle_pass_season', 'medal_id', 'medal', 'id', 'SET NULL', 'CASCADE');

        $this->execute("ALTER TABLE `tasks_v2` MODIFY `type` ENUM('one_time','repeatable','daily_reward','battle_pass') NOT NULL DEFAULT 'one_time'");
        $this->addColumn('tasks_v2', 'battle_pass_season_id', self::INT_FIELD . ' AFTER `type`');
        $this->addColumn('tasks_v2', 'battle_pass_position', 'INT(10) UNSIGNED DEFAULT NULL AFTER `battle_pass_season_id`');
        $this->createIndex('idx-tasks_v2-battle_pass_position', 'tasks_v2', ['battle_pass_season_id', 'battle_pass_position'], true);
        $this->addForeignKey('fk-tasks_v2-battle_pass_season_id', 'tasks_v2', 'battle_pass_season_id', 'battle_pass_season', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('battle_pass_user_task', [
            'id' => self::PRIMARY_KEY,
            'season_id' => self::INT_FIELD_NOT_NULL,
            'task_id' => self::INT_FIELD_NOT_NULL,
            'user_id' => self::INT_FIELD_NOT_NULL,
            'baseline_value' => 'BIGINT(20) UNSIGNED NOT NULL DEFAULT 0',
            'unlocked_at' => 'DATETIME NOT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-battle_pass_user_task-user_task', 'battle_pass_user_task', ['user_id', 'task_id'], true);
        $this->createIndex('idx-battle_pass_user_task-season_user', 'battle_pass_user_task', ['season_id', 'user_id']);
        $this->addForeignKey('fk-battle_pass_user_task-season_id', 'battle_pass_user_task', 'season_id', 'battle_pass_season', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-battle_pass_user_task-task_id', 'battle_pass_user_task', 'task_id', 'tasks_v2', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-battle_pass_user_task-user_id', 'battle_pass_user_task', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('battle_pass_user_season', [
            'id' => self::PRIMARY_KEY,
            'season_id' => self::INT_FIELD_NOT_NULL,
            'user_id' => self::INT_FIELD_NOT_NULL,
            'completed_at' => 'DATETIME NOT NULL',
            'reward_given_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('idx-battle_pass_user_season-season_user', 'battle_pass_user_season', ['season_id', 'user_id'], true);
        $this->createIndex('idx-battle_pass_user_season-user_id', 'battle_pass_user_season', 'user_id');
        $this->addForeignKey('fk-battle_pass_user_season-season_id', 'battle_pass_user_season', 'season_id', 'battle_pass_season', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-battle_pass_user_season-user_id', 'battle_pass_user_season', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('battle_pass_user_season');
        $this->dropTable('battle_pass_user_task');
        $this->dropForeignKey('fk-tasks_v2-battle_pass_season_id', 'tasks_v2');
        $this->dropIndex('idx-tasks_v2-battle_pass_position', 'tasks_v2');
        $this->dropColumn('tasks_v2', 'battle_pass_position');
        $this->dropColumn('tasks_v2', 'battle_pass_season_id');
        $this->execute("ALTER TABLE `tasks_v2` MODIFY `type` ENUM('one_time','repeatable','daily_reward') NOT NULL DEFAULT 'one_time'");
        $this->dropTable('battle_pass_season');
        $this->dropTable('user_medal');
        $this->dropTable('medal');
    }
}

<?php

use console\components\migration\Migration;

/**
 * Updates existing radio tables to match the new structure
 */
class m251027_222500_update_radio_tables extends Migration
{
    public function safeUp()
    {
        // Check and add missing columns to radio_station
        $this->addColumnIfNotExists('radio_station', 'folder_name', 'VARCHAR(255) NOT NULL DEFAULT "1" AFTER port');
        $this->addColumnIfNotExists('radio_station', 'status', 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 1 AFTER folder_name');
        $this->addColumnIfNotExists('radio_station', 'is_running', 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER status');
        $this->addColumnIfNotExists('radio_station', 'current_track_id', 'INT(10) UNSIGNED DEFAULT NULL AFTER is_running');
        $this->addColumnIfNotExists('radio_station', 'listeners_count', 'INT(10) UNSIGNED DEFAULT 0 AFTER current_track_id');
        $this->addColumnIfNotExists('radio_station', 'updated_at', 'TIMESTAMP NULL AFTER created_at');

        // Create radio_track table if not exists
        $this->createTableIfNotExists('radio_track', [
            'id'         => self::PRIMARY_KEY,
            'radio_station_id' => self::INT_FIELD_NOT_NULL,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'title'      => 'VARCHAR(255) NOT NULL',
            'artist'     => 'VARCHAR(255) DEFAULT NULL',
            'filename'   => 'VARCHAR(255) NOT NULL',
            'duration'   => 'INT(10) UNSIGNED DEFAULT NULL',
            'status'     => self::TINYINT_FIELD . ' DEFAULT 3',
            'likes'      => 'INT(10) UNSIGNED DEFAULT 0',
            'plays'      => 'INT(10) UNSIGNED DEFAULT 0',
            'created_at' => self::TIMESTAMP_FIELD,
        ]);

        // Create radio_track_like table if not exists
        $this->createTableIfNotExists('radio_track_like', [
            'id'         => self::PRIMARY_KEY,
            'radio_track_id' => self::INT_FIELD_NOT_NULL,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'type'       => self::TINYINT_FIELD . ' DEFAULT 1',
            'created_at' => self::TIMESTAMP_FIELD,
        ]);

        // Add indexes if not exist
        $this->createIndexIfNotExists('idx_radio_station_folder', 'radio_station', 'folder_name', true);
        $this->createIndexIfNotExists('idx_radio_station_port', 'radio_station', 'port', true);
        $this->createIndexIfNotExists('idx_radio_track_status', 'radio_track', 'status');
        $this->createIndexIfNotExists('idx_radio_track_station', 'radio_track', 'radio_station_id');
        $this->createIndexIfNotExists('idx_radio_track_user', 'radio_track', 'user_id');
        $this->createIndexIfNotExists('idx_radio_track_like_unique', 'radio_track_like', ['radio_track_id', 'user_id'], true);

        // Add foreign keys if not exist
        $this->addForeignKeyIfNotExists('fk_radio_track_station', 'radio_track', 'radio_station_id', 'radio_station', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfNotExists('fk_radio_track_user', 'radio_track', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfNotExists('fk_radio_track_like_track', 'radio_track_like', 'radio_track_id', 'radio_track', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfNotExists('fk_radio_track_like_user', 'radio_track_like', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfNotExists('fk_radio_station_current_track', 'radio_station', 'current_track_id', 'radio_track', 'id', 'SET NULL', 'CASCADE');

        return true;
    }

    public function safeDown()
    {
        echo "m251027_222500_update_radio_tables cannot be reverted.\n";
        return false;
    }

    // Helper methods
    protected function addColumnIfNotExists($table, $column, $type)
    {
        try {
            $exists = $this->db->createCommand("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->queryOne();
            if (!$exists) {
                $this->addColumn($table, $column, $type);
                echo "  > Added column '{$column}' to table '{$table}'\n";
            } else {
                echo "  > Column '{$column}' already exists in table '{$table}'\n";
            }
        } catch (\Exception $e) {
            echo "  > Error checking column '{$column}': " . $e->getMessage() . "\n";
        }
    }

    protected function createTableIfNotExists($table, $columns)
    {
        try {
            $exists = $this->db->createCommand("SHOW TABLES LIKE '{$table}'")->queryScalar();
            if (!$exists) {
                $this->createTable($table, $columns, self::TABLE_OPTIONS);
                echo "  > Created table '{$table}'\n";
            } else {
                echo "  > Table '{$table}' already exists\n";
            }
        } catch (\Exception $e) {
            echo "  > Error creating table '{$table}': " . $e->getMessage() . "\n";
        }
    }

    protected function createIndexIfNotExists($name, $table, $columns, $unique = false)
    {
        try {
            $indexExists = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'")->queryOne();
            if (!$indexExists) {
                $this->createIndex($name, $table, $columns, $unique);
                echo "  > Created index '{$name}' on table '{$table}'\n";
            } else {
                echo "  > Index '{$name}' already exists on table '{$table}'\n";
            }
        } catch (\Exception $e) {
            echo "  > Error creating index '{$name}': " . $e->getMessage() . "\n";
        }
    }

    protected function addForeignKeyIfNotExists($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        try {
            $fkExists = $this->db->createCommand("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}' 
                AND CONSTRAINT_NAME = '{$name}'
            ")->queryOne();
            
            if (!$fkExists) {
                $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
                echo "  > Added foreign key '{$name}' to table '{$table}'\n";
            } else {
                echo "  > Foreign key '{$name}' already exists on table '{$table}'\n";
            }
        } catch (\Exception $e) {
            echo "  > Error adding foreign key '{$name}': " . $e->getMessage() . "\n";
        }
    }
}



<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `rust_plugin_configs`.
 */
class m260101_130000_create_rust_plugin_configs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('rust_plugin_configs', [
            'id' => self::PRIMARY_KEY,
            'name' => 'VARCHAR(255) NOT NULL COMMENT \'Название конфига\'',
            'content' => 'TEXT NOT NULL COMMENT \'JSON содержимое конфига\'',
            'created_at' => self::TIMESTAMP_FIELD,
            'updated_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-rust_plugin_configs-name', 'rust_plugin_configs', 'name');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('rust_plugin_configs');
    }
}


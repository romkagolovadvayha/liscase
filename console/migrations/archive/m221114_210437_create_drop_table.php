<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%drop}}`.
 */
class m221114_210437_create_drop_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%drop}}', [
            'id'             => self::PRIMARY_KEY,
            'name'        => self::VARCHAR_FIELD,
            'image' => self::VARCHAR_FIELD,
            'price' => 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0',
            'status'          => self::TINYINT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%drop}}');
    }
}

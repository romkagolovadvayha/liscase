<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%user_balance}}`.
 */
class m221114_210459_create_user_balance_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%user_balance}}', [
            'id'             => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'type' => self::INT_FIELD,
            'balance'        => 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0',
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%user_balance}}');
    }
}

<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%profit}}`.
 */
class m221114_210446_create_profit_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%profit}}', [
            'id'             => self::PRIMARY_KEY,
            'user_balance_id'        => self::INT_FIELD_NOT_NULL,
            'type' => self::INT_FIELD,
            'amount'            => 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0',
            'comment'            => self::VARCHAR_FIELD,
            'status'          => self::TINYINT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%profit}}');
    }
}

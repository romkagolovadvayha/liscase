<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%user_box}}`.
 */
class m221127_213823_create_user_box_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%user_box}}', [
            'id'             => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD,
            'box_id'        => self::INT_FIELD,
            'status'         => self::INT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%user_box}}');
    }
}

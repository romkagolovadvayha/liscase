<?php

use console\components\migration\Migration;

/**
 * Class m241026_091551_support
 */
class m241026_091551_support extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('support', [
            'id'         => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'name'    => 'VARCHAR(255) DEFAULT NULL',
            'status'          => self::TINYINT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('support_user_id', 'support', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('support_message', [
            'id'         => self::PRIMARY_KEY,
            'support_id'     => self::INT_FIELD_NOT_NULL,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'message'    => 'TEXT NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('support_message_user_id', 'support_message', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('support_message_support_id', 'support_message', 'support_id',
                             'support', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('support_file', [
            'id'         => self::PRIMARY_KEY,
            'support_message_id'     => self::INT_FIELD_NOT_NULL,
            'file'      => 'VARCHAR(512) NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('support_file_support_message_id', 'support_file', 'support_message_id',
                             'support_message', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241026_091551_support cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241026_091551_support cannot be reverted.\n";

        return false;
    }
    */
}

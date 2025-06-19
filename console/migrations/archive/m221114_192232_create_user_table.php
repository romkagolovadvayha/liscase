<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `user`.
 */
class m221114_192232_create_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user', [
            'id'                   => self::PRIMARY_KEY,
            'email'                => $this->string()->notNull()->unique(),
            'email_confirm_token'  => $this->char(32)->null(),
            'password_hash'        => $this->string()->notNull(),
            'password_reset_token' => $this->string()->null(),
            'auth_key'             => $this->char(32)->notNull(),
            'ref_code'             => self::INT_FIELD_NOT_NULL,
            'socket_room'          => $this->string(32)->notNull(),
            'current_language'     => 'VARCHAR(10) NOT NULL DEFAULT "ru-RU"',
            'status'               => self::TINYINT_FIELD,
            'created_at'           => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('user');
    }
}

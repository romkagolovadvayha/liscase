<?php

use console\components\migration\Migration;

/**
 * Class m241009_083222_rustoteka_bot
 */
class m241009_083222_rustoteka_bot extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('telegram_user', [
            'id'         => self::PRIMARY_KEY,
            'name'    => 'VARCHAR(255) DEFAULT NULL',
            'username'    => 'VARCHAR(255) DEFAULT NULL',
            'chat_id'    => 'VARCHAR(40) DEFAULT NULL',
            'steam_id'   => 'VARCHAR(19) DEFAULT NULL',
            'type'    => self::INT_FIELD_NOT_NULL,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('ban_list', [
            'id'         => self::PRIMARY_KEY,
            'steam_id'   => 'VARCHAR(19) DEFAULT NULL',
            'project_name' => 'VARCHAR(255) DEFAULT NULL',
            'server_name' => 'VARCHAR(255) DEFAULT NULL',
            'reason' => 'VARCHAR(255) DEFAULT NULL',
            'banned_at' => self::TIMESTAMP_FIELD,
            'unbanned_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('telegram_message', [
            'id'         => self::PRIMARY_KEY,
            'chat_id'    => 'VARCHAR(19) DEFAULT NULL',
            'message' => 'VARCHAR(512) DEFAULT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241009_083222_rustoteka_bot cannot be reverted.\n";

        return false;
    }
    */
}

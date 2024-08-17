<?php

use console\components\migration\Migration;

/**
 * Class m240817_191446_chat_and_reports
 */
class m240817_191446_chat_and_reports extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_reports}}', [
            'id'                 => self::PRIMARY_KEY,
            'steam_id'           => self::VARCHAR_FIELD,
            'recepient_steam_id' => self::VARCHAR_FIELD,
            'reason'             => self::VARCHAR_FIELD,
            'created_at'         => self::VARCHAR_FIELD,
            'server_tag'         => self::VARCHAR_FIELD,
            'wipe'               => self::VARCHAR_FIELD,
        ]);
        $this->createTable('{{%servers_chats}}', [
            'id'         => self::PRIMARY_KEY,
            'steam_id'   => self::VARCHAR_FIELD,
            'message'    => self::VARCHAR_FIELD,
            'created_at' => self::VARCHAR_FIELD,
            'server_tag' => self::VARCHAR_FIELD,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240817_191446_chat_and_reports cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240817_191446_chat_and_reports cannot be reverted.\n";

        return false;
    }
    */
}

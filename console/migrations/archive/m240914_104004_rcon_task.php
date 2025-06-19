<?php

use console\components\migration\Migration;

/**
 * Class m240914_104004_rcon_task
 */
class m240914_104004_rcon_task extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('rcon_tasks', [
            'id'         => self::PRIMARY_KEY,
            'command'    => self::VARCHAR_FIELD,
            'status'    => self::TINYINT_1_FIELD,
            'server_tag'    => 'VARCHAR(30) DEFAULT NULL',
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
        echo "m240914_104004_rcon_task cannot be reverted.\n";

        return false;
    }
    */
}

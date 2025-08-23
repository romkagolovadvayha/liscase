<?php

use console\components\migration\Migration;

/**
 * Class m250823_211625_servers_chats_is_muted
 */
class m250823_211625_servers_chats_is_muted extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers_chats','is_muted', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250823_211625_servers_chats_is_muted cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250823_211625_servers_chats_is_muted cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250201_110641_rcon_tasks
 */
class m250201_110641_rcon_tasks extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('rcon_tasks', 'command', 'TEXT DEFAULT NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250201_110641_rcon_tasks cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250201_110641_rcon_tasks cannot be reverted.\n";

        return false;
    }
    */
}

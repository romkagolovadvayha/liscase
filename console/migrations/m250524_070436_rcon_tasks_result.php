<?php

use console\components\migration\Migration;

/**
 * Class m250524_070436_rcon_tasks_result
 */
class m250524_070436_rcon_tasks_result extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('rcon_tasks', 'result', 'VARCHAR(512) DEFAULT NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250524_070436_rcon_tasks_result cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250524_070436_rcon_tasks_result cannot be reverted.\n";

        return false;
    }
    */
}

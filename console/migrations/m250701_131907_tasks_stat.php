<?php

use console\components\migration\Migration;
/**
 * Class m250701_131907_tasks_stat
 */
class m250701_131907_tasks_stat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('tasks', 'stat_param', self::VARCHAR_FIELD);
        $this->addColumn('tasks', 'stat_count', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250701_131907_tasks_stat cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250701_131907_tasks_stat cannot be reverted.\n";

        return false;
    }
    */
}

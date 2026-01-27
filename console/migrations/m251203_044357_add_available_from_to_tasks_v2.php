<?php

use yii\db\Migration;

/**
 * Class m251203_044357_add_available_from_to_tasks_v2
 */
class m251203_044357_add_available_from_to_tasks_v2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('tasks_v2', 'available_from', 'DATETIME DEFAULT NULL COMMENT \'Дата и время, когда задание станет доступно (NULL = доступно сразу)\'');
        $this->createIndex('idx-tasks_v2-available_from', 'tasks_v2', 'available_from');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-tasks_v2-available_from', 'tasks_v2');
        $this->dropColumn('tasks_v2', 'available_from');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251203_044357_add_available_from_to_tasks_v2 cannot be reverted.\n";

        return false;
    }
    */
}

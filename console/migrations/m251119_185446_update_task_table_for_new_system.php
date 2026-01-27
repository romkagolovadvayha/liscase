<?php

use yii\db\Migration;

/**
 * Class m251119_185446_update_task_table_for_new_system
 */
class m251119_185446_update_task_table_for_new_system extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m251119_185446_update_task_table_for_new_system cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251119_185446_update_task_table_for_new_system cannot be reverted.\n";

        return false;
    }
    */
}

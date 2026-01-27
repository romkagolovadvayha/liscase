<?php

use yii\db\Migration;

/**
 * Class m251119_185558_update_user_task_table_for_wipe_tasks
 */
class m251119_185558_update_user_task_table_for_wipe_tasks extends Migration
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
        echo "m251119_185558_update_user_task_table_for_wipe_tasks cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251119_185558_update_user_task_table_for_wipe_tasks cannot be reverted.\n";

        return false;
    }
    */
}

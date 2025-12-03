<?php

use yii\db\Migration;

/**
 * Class m251203_043549_add_is_vip_only_to_tasks_v2
 */
class m251203_043549_add_is_vip_only_to_tasks_v2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('tasks_v2', 'is_vip_only', 'TINYINT(1) DEFAULT 0 COMMENT \'Доступно только для VIP пользователей (0-нет, 1-да)\'');
        $this->createIndex('idx-tasks_v2-is_vip_only', 'tasks_v2', 'is_vip_only');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-tasks_v2-is_vip_only', 'tasks_v2');
        $this->dropColumn('tasks_v2', 'is_vip_only');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251203_043549_add_is_vip_only_to_tasks_v2 cannot be reverted.\n";

        return false;
    }
    */
}

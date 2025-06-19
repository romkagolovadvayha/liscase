<?php

use console\components\migration\Migration;

/**
 * Class m250416_165814_drop_drop
 */
class m250416_165814_drop_drop extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_drop','parent_drop_id', self::INT_FIELD);
        $this->addForeignKey('user_drop_parent_drop_id', 'drop_drop', 'parent_drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250416_165814_drop_drop cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250416_165814_drop_drop cannot be reverted.\n";

        return false;
    }
    */
}

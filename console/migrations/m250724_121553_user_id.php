<?php

use console\components\migration\Migration;

/**
 * Class m250724_121553_user_id
 */
class m250724_121553_user_id extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','user_id', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250724_121553_user_id cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250724_121553_user_id cannot be reverted.\n";

        return false;
    }
    */
}

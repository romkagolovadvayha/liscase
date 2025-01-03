<?php

use console\components\migration\Migration;

/**
 * Class m250102_161253_user_store
 */
class m250102_161253_user_store extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','store', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250102_161253_user_store cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250102_161253_user_store cannot be reverted.\n";

        return false;
    }
    */
}

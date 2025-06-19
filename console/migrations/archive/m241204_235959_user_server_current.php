<?php

use console\components\migration\Migration;

/**
 * Class m241204_235959_user_server_current
 */
class m241204_235959_user_server_current extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','server_id', 'INT(11) DEFAULT NULL');

        $this->addForeignKey('user_server_id', 'user', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241204_235959_user_server_current cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241204_235959_user_server_current cannot be reverted.\n";

        return false;
    }
    */
}

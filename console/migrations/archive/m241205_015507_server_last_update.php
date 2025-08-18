<?php

use console\components\migration\Migration;

/**
 * Class m241205_015507_server_last_update
 */
class m241205_015507_server_last_update extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','updated_at', self::TIMESTAMP_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241205_015507_server_last_update cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241205_015507_server_last_update cannot be reverted.\n";

        return false;
    }
    */
}

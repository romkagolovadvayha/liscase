<?php

use console\components\migration\Migration;

/**
 * Class m241205_110701_user_updated_at
 */
class m241205_110701_user_updated_at extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','last_visit_server_at', self::TIMESTAMP_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241205_110701_user_updated_at cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241205_110701_user_updated_at cannot be reverted.\n";

        return false;
    }
    */
}

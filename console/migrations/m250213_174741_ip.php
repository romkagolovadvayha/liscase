<?php

use console\components\migration\Migration;

/**
 * Class m250213_174741_ip
 */
class m250213_174741_ip extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','ip', self::VARCHAR_FIELD);
        $this->addColumn('user','ping', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250213_174741_ip cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250213_174741_ip cannot be reverted.\n";

        return false;
    }
    */
}

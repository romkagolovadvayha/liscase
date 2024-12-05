<?php

use yii\db\Migration;

/**
 * Class m241205_003919_servers_sort
 */
class m241205_003919_servers_sort extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','sort', 'INT(10) DEFAULT 0');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241205_003919_servers_sort cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241205_003919_servers_sort cannot be reverted.\n";

        return false;
    }
    */
}

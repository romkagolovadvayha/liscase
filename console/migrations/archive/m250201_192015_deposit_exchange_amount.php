<?php

use console\components\migration\Migration;

/**
 * Class m250201_192015_deposit_exchange_amount
 */
class m250201_192015_deposit_exchange_amount extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('deposit','amount_exchange', 'DECIMAL(14,2) DEFAULT NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250201_192015_deposit_exchange_amount cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250201_192015_deposit_exchange_amount cannot be reverted.\n";

        return false;
    }
    */
}

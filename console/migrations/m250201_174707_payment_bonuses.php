<?php

use console\components\migration\Migration;

/**
 * Class m250201_174707_payment_bonuses
 */
class m250201_174707_payment_bonuses extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('payment_bonuses', [
            'id'           => self::PRIMARY_KEY,
            'amount'     => self::INT_FIELD,
            'bonus'         => self::INT_FIELD,
            'created_at'   => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->insert('payment_bonuses', ['amount' => 500, 'bonus' => 15, 'created_at' => date('Y-m-d H:i:s')]);
        $this->insert('payment_bonuses', ['amount' => 1000, 'bonus' => 20, 'created_at' => date('Y-m-d H:i:s')]);
        $this->insert('payment_bonuses', ['amount' => 1500, 'bonus' => 25, 'created_at' => date('Y-m-d H:i:s')]);
        $this->insert('payment_bonuses', ['amount' => 2000, 'bonus' => 30, 'created_at' => date('Y-m-d H:i:s')]);
        $this->insert('payment_bonuses', ['amount' => 5000, 'bonus' => 50, 'created_at' => date('Y-m-d H:i:s')]);
        $this->insert('payment_bonuses', ['amount' => 20000, 'bonus' => 100, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250201_174707_payment_bonuses cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250201_174707_payment_bonuses cannot be reverted.\n";

        return false;
    }
    */
}

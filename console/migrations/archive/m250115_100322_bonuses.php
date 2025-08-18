<?php

use console\components\migration\Migration;

/**
 * Class m250115_100322_bonuses
 */
class m250115_100322_bonuses extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('deposit_bonus', [
            'id'           => self::PRIMARY_KEY,
            'bonus'         => self::INT_FIELD_NOT_NULL,
            'min_amount'         => self::INT_FIELD_NOT_NULL,
        ], self::TABLE_OPTIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250115_100322_bonuses cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250115_100322_bonuses cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250221_111155_promo
 */
class m250221_111155_promo extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','promocode', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250221_111155_promo cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250221_111155_promo cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250114_190613_support_read
 */
class m250114_190613_support_read extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support_read', 'status', self::TINYINT_1_FIELD);
        $this->addColumn('support_read', 'support_id', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250114_190613_support_read cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250110_012538_suppeor_user
 */
class m250110_012538_suppeor_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('support_message', 'user_id', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250110_012538_suppeor_user cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250110_012538_suppeor_user cannot be reverted.\n";

        return false;
    }
    */
}

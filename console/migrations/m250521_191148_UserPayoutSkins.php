<?php

use console\components\migration\Migration;

/**
 * Class m250521_191148_UserPayoutSkins
 */
class m250521_191148_UserPayoutSkins extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_payout_skins', 'type', 'VARCHAR(255) DEFAULT "rust"');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250521_191148_UserPayoutSkins cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250521_191148_UserPayoutSkins cannot be reverted.\n";

        return false;
    }
    */
}

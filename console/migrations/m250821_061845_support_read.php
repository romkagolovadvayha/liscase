<?php

use console\components\migration\Migration;

/**
 * Class m250821_061845_support_read
 */
class m250821_061845_support_read extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support_read', 'id', self::PRIMARY_KEY . ' FIRST');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250821_061845_support_read cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250821_061845_support_read cannot be reverted.\n";

        return false;
    }
    */
}

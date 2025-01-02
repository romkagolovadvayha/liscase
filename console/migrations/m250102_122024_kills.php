<?php

use console\components\migration\Migration;

/**
 * Class m250102_122024_kills
 */
class m250102_122024_kills extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('statistics_kills','signs', self::VARCHAR_FIELD . ' AFTER dead');
        $this->addColumn('statistics_kills','wears', self::VARCHAR_FIELD . ' AFTER dead');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250102_122024_kills cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250102_122024_kills cannot be reverted.\n";

        return false;
    }
    */
}

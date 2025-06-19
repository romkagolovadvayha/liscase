<?php

use console\components\migration\Migration;

/**
 * Class m250416_160142_drop_drop
 */
class m250416_160142_drop_drop extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('drop_drop','count', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250416_160142_drop_drop cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250416_160142_drop_drop cannot be reverted.\n";

        return false;
    }
    */
}

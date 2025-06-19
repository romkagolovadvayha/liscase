<?php

use console\components\migration\Migration;

/**
 * Class m250115_183408_user_map
 */
class m250115_183408_user_map extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('map', 'votes', self::INT_FIELD_NOT_NULL);
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
        echo "m250115_183408_user_map cannot be reverted.\n";

        return false;
    }
    */
}

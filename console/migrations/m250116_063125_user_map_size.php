<?php

use console\components\migration\Migration;

/**
 * Class m250116_063125_user_map_size
 */
class m250116_063125_user_map_size extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers', 'min_map_size', 'INT(10) UNSIGNED DEFAULT 3750');
        $this->addColumn('servers', 'max_map_size', 'INT(10) UNSIGNED DEFAULT 4250');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250116_063125_user_map_size cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250116_063125_user_map_size cannot be reverted.\n";

        return false;
    }
    */
}

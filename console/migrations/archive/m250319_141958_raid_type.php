<?php

use console\components\migration\Migration;

/**
 * Class m250319_141958_raid_type
 */
class m250319_141958_raid_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_raid','type', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250319_141958_raid_type cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250319_141958_raid_type cannot be reverted.\n";

        return false;
    }
    */
}

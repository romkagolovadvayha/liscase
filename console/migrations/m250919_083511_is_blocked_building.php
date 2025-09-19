<?php

use console\components\migration\Migration;

/**
 * Class m250919_083511_is_blocked_building
 */
class m250919_083511_is_blocked_building extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('drop','is_blocked_building', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250919_083511_is_blocked_building cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250919_083511_is_blocked_building cannot be reverted.\n";

        return false;
    }
    */
}

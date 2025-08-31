<?php

use console\components\migration\Migration;

/**
 * Class m250823_110654_telegram_blocked
 */
class m250823_110654_telegram_blocked extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','is_telegram_blocked', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250823_110654_telegram_blocked cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250823_110654_telegram_blocked cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m260205_232045_add_is_wipe_calendar_visitor_to_user_table
 */
class m260205_232045_add_is_wipe_calendar_visitor_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'is_wipe_calendar_visitor', $this->boolean()->defaultValue(0)->after('is_telegram_blocked'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('user', 'is_wipe_calendar_visitor');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260205_232045_add_is_wipe_calendar_visitor_to_user_table cannot be reverted.\n";

        return false;
    }
    */
}

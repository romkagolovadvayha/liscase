<?php

use console\components\migration\Migration;

/**
 * Class m250119_122156_user_status_description
 */
class m250119_122156_user_status_description extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'stat_status', self::VARCHAR_FIELD);
        $this->addColumn('user', 'avatar_frame', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250119_122156_user_status_description cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250119_122156_user_status_description cannot be reverted.\n";

        return false;
    }
    */
}

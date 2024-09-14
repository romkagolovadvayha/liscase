<?php

use console\components\migration\Migration;

/**
 * Class m240914_132045_user_columns
 */
class m240914_132045_user_columns extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','ban_reason', 'TINYINT(3) UNSIGNED DEFAULT NULL');
        $this->addColumn('user','ban_by', self::INT_FIELD);
        $this->addColumn('user','banned_at', self::TIMESTAMP_FIELD);
        $this->addColumn('user','unbanned_at', self::TIMESTAMP_FIELD);
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
        echo "m240914_132045_user_columns cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250108_130727_user_stats_desabled
 */
class m250108_130727_user_stats_desabled extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','is_stats', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250108_130727_user_stats_desabled cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250108_130727_user_stats_desabled cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250626_121308_is_bot
 */
class m250626_121308_is_bot extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support','is_bot', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250626_121308_is_bot cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250626_121308_is_bot cannot be reverted.\n";

        return false;
    }
    */
}

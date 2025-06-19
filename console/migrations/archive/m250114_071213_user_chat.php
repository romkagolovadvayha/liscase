<?php

use console\components\migration\Migration;

/**
 * Class m250114_071213_user_chat
 */
class m250114_071213_user_chat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'blocked_support_at', self::TIMESTAMP_FIELD);
        $this->addColumn('user', 'blocked_support', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250114_071213_user_chat cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250114_071213_user_chat cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250122_163510_user_is_email
 */
class m250122_163510_user_is_email extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','is_email', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250122_163510_user_is_email cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250122_163510_user_is_email cannot be reverted.\n";

        return false;
    }
    */
}

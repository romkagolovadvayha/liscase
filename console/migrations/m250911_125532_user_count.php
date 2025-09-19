<?php

use console\components\migration\Migration;

/**
 * Class m250911_125532_user_count
 */
class m250911_125532_user_count extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->addColumn('clan','user_count', self::INT_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250911_125532_user_count cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250911_125532_user_count cannot be reverted.\n";

        return false;
    }
    */
}

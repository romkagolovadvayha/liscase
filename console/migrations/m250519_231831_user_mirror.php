<?php

use console\components\migration\Migration;

/**
 * Class m250519_231831_user_mirror
 */
class m250519_231831_user_mirror extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'is_mirror_registration', self::TINYINT_1_FIELD);
        $this->addColumn('user', 'is_mirror_returned', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250519_231831_user_mirror cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250519_231831_user_mirror cannot be reverted.\n";

        return false;
    }
    */
}

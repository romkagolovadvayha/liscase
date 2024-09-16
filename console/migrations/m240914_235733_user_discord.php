<?php

use console\components\migration\Migration;

/**
 * Class m240914_235733_user_discord
 */
class m240914_235733_user_discord extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','discord', self::VARCHAR_FIELD);
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
        echo "m240914_235733_user_discord cannot be reverted.\n";

        return false;
    }
    */
}

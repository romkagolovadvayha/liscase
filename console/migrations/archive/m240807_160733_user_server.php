<?php

use yii\db\Migration;

/**
 * Class m240807_160733_user_server
 */
class m240807_160733_user_server extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_profile','server_tag', 'VARCHAR(255) DEFAULT "max3" AFTER referral_click');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240807_160733_user_server cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240807_160733_user_server cannot be reverted.\n";

        return false;
    }
    */
}

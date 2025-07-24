<?php

use yii\db\Migration;

/**
 * Class m250724_141543_user_fileds2
 */
class m250724_141543_user_fileds2 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        Yii::$app->db->createCommand("
            ALTER TABLE `user` ADD INDEX `username` (`username`);
                ")->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250724_141543_user_fileds2 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250724_141543_user_fileds2 cannot be reverted.\n";

        return false;
    }
    */
}

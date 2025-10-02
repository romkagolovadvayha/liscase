<?php

use yii\db\Migration;

/**
 * Class m251001_122649_update_clan_tag_length
 */
class m251001_122649_update_clan_tag_length extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m251001_122649_update_clan_tag_length cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251001_122649_update_clan_tag_length cannot be reverted.\n";

        return false;
    }
    */
}

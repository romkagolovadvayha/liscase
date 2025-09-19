<?php

use yii\db\Migration;

/**
 * Class m250911_164740_user_count
 */
class m250911_164740_user_count extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_clan','steam_id', 'VARCHAR(19) DEFAULT NULL');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250911_164740_user_count cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250911_164740_user_count cannot be reverted.\n";

        return false;
    }
    */
}

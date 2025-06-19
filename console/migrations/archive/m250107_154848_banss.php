<?php

use yii\db\Migration;

/**
 * Class m250107_154848_banss
 */
class m250107_154848_banss extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('bans','steam_id', 'VARCHAR(19) NOT NULL AFTER user_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250107_154848_banss cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250107_154848_banss cannot be reverted.\n";

        return false;
    }
    */
}

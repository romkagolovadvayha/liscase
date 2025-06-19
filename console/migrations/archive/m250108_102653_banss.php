<?php

use yii\db\Migration;

/**
 * Class m250108_102653_banss
 */
class m250108_102653_banss extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('bans','username', 'VARCHAR(255) NOT NULL AFTER id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250108_102653_banss cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250108_102653_banss cannot be reverted.\n";

        return false;
    }
    */
}

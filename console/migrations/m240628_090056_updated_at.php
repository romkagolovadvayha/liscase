<?php

use yii\db\Migration;

/**
 * Class m240628_090056_updated_at
 */
class m240628_090056_updated_at extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() AFTER created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240628_090056_updated_at cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240628_090056_updated_at cannot be reverted.\n";

        return false;
    }
    */
}

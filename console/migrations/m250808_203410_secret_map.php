<?php

use yii\db\Migration;

/**
 * Class m250808_203410_secret_map
 */
class m250808_203410_secret_map extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers', 'secret_map', $this->boolean()->notNull()->defaultValue(0)
                                                   ->comment('Секретная карта'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250808_203410_secret_map cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250808_203410_secret_map cannot be reverted.\n";

        return false;
    }
    */
}

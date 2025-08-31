<?php

use console\components\migration\Migration;

/**
 * Class m250827_064238_image_64
 */
class m250827_064238_image_64 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('server_skin','image_64', self::VARCHAR_FIELD);
        $this->addColumn('server_skin','image_150', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250827_064238_image_64 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250827_064238_image_64 cannot be reverted.\n";

        return false;
    }
    */
}

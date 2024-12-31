<?php

use console\components\migration\Migration;

/**
 * Class m241226_155424_server_text
 */
class m241226_155424_server_text extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','monitoring_name', self::VARCHAR_FIELD);
        $this->addColumn('servers','monitoring_description', self::VARCHAR_FIELD);
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
        echo "m241226_155424_server_text cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m251028_084627_add_stream_url_to_radio_station
 */
class m251028_084627_add_stream_url_to_radio_station extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('radio_station', 'stream_url', 'VARCHAR(255) DEFAULT NULL COMMENT "URL для потока (альтернатива localhost)"');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('radio_station', 'stream_url');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251028_084627_add_stream_url_to_radio_station cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m250218_231226_linmk
 */
class m250218_231226_linmk extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('clan','link_hash', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250218_231226_linmk cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250218_231226_linmk cannot be reverted.\n";

        return false;
    }
    */
}

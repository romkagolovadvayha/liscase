<?php

use console\components\migration\Migration;

/**
 * Class m250506_074040_sended_at
 */
class m250506_074040_sended_at extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user_drop','sended_at', self::TIMESTAMP_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250506_074040_sended_at cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250506_074040_sended_at cannot be reverted.\n";

        return false;
    }
    */
}

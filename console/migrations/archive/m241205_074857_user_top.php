<?php

use console\components\migration\Migration;

/**
 * Class m241205_074857_user_top
 */
class m241205_074857_user_top extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_top', [
            'id'         => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'key'     => self::VARCHAR_FIELD,
            'value'     => 'DECIMAL(14,2) NOT NULL DEFAULT 0',
            'server_id'     => 'INT(11) NOT NULL',
            'wipe'       => self::VARCHAR_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('user_top_server_id', 'user_top', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_top_user_id', 'user_top', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241205_074857_user_top cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241205_074857_user_top cannot be reverted.\n";

        return false;
    }
    */
}

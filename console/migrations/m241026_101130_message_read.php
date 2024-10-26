<?php

use console\components\migration\Migration;

/**
 * Class m241026_101130_message_read
 */
class m241026_101130_message_read extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('support_read', [
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'support_message_id'     => self::INT_FIELD_NOT_NULL
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('support_read_user_id', 'support_read', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('support_read_support_message_id', 'support_read', 'support_message_id',
                             'support_message', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241026_101130_message_read cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241026_101130_message_read cannot be reverted.\n";

        return false;
    }
    */
}

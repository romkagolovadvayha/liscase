<?php

use console\components\migration\Migration;

/**
 * Class m240914_222501_report_checking
 */
class m240914_222501_report_checking extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_checking', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'status'    => self::TINYINT_1_FIELD,
            'checking_by'    => self::INT_FIELD_NOT_NULL,
            'created_at' => self::TIMESTAMP_FIELD,
            'done_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('user_checking_user_id', 'user_checking', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('user_checking_checking_by', 'user_checking', 'checking_by',
                             'user', 'id', 'CASCADE', 'CASCADE');
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
        echo "m240914_222501_report_checking cannot be reverted.\n";

        return false;
    }
    */
}

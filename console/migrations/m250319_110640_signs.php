<?php

use console\components\migration\Migration;

/**
 * Class m250319_110640_signs
 */
class m250319_110640_signs extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('signs', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'signId'     => self::INT_FIELD_NOT_NULL,
            'status'     => self::TINYINT_1_FIELD,
            'type'       => self::VARCHAR_FIELD,
            'image'      => self::VARCHAR_FIELD,
            'position'   => self::VARCHAR_FIELD,
            'server_id'  => 'INT(11) NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('signs_user_id', 'signs', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('signs_server_id', 'signs', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250319_110640_signs cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250319_110640_signs cannot be reverted.\n";

        return false;
    }
    */
}

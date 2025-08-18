<?php

use console\components\migration\Migration;

/**
 * Class m240909_121812_confim_code
 */
class m240909_121812_confim_code extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $this->createTable('user_confirm_code', [
//            'id'         => self::PRIMARY_KEY,
//            'user_id'    => self::INT_FIELD_NOT_NULL,
//            'type'       => 'TINYINT(1) UNSIGNED NOT NULL',
//            'code'       => 'VARCHAR(64) NOT NULL',
//            'status'     => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
//            'created_at' => self::TIMESTAMP_FIELD,
//        ], self::TABLE_OPTIONS);
//
//        $this->addForeignKey('user_confirm_code_user_id', 'user_confirm_code', 'user_id',
//                             'user', 'id', 'CASCADE', 'CASCADE');
//
//        $this->createIndex('index_user_type_status', 'user_confirm_code', 'user_id,type,status');

        $this->addColumn('user','telegram_chat_id', 'VARCHAR(40) DEFAULT NULL AFTER steam_id');
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
        echo "m240909_121812_confim_code cannot be reverted.\n";

        return false;
    }
    */
}

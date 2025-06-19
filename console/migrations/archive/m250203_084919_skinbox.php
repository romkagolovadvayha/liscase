<?php

use console\components\migration\Migration;

/**
 * Class m250203_084919_skinbox
 */
class m250203_084919_skinbox extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('server_skin', [
            'id'         => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'name'    => 'VARCHAR(255) DEFAULT NULL',
            'status'          => self::TINYINT_FIELD,
            'image'          => self::VARCHAR_FIELD,
            'likes'          => 'INT(10) UNSIGNED DEFAULT 0',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('server_skin_like', [
            'id'         => self::PRIMARY_KEY,
            'server_skin_id'     => self::INT_FIELD_NOT_NULL,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'type'          => self::TINYINT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('server_skin_like_server_skin_id', 'server_skin_like', 'server_skin_id',
                             'server_skin', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('server_skin_user_id', 'server_skin', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('server_skin_like_user_id', 'server_skin_like', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('server_skin', 'skin_id', self::INT_FIELD_NOT_NULL);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250203_084919_skinbox cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250203_084919_skinbox cannot be reverted.\n";

        return false;
    }
    */
}

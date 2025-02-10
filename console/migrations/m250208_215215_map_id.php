<?php

use console\components\migration\Migration;

/**
 * Class m250208_215215_map_id
 */
class m250208_215215_map_id extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('drop_blocked', [
            'id'         => self::PRIMARY_KEY,
            'drop_id'        => self::INT_FIELD_NOT_NULL,
            'server_id'    => 'INT(11) NOT NULL',
            'blocked_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('drop_blocked_drop_id', 'drop_blocked', 'drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('drop_blocked_server_id', 'drop_blocked', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250208_215215_map_id cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250208_215215_map_id cannot be reverted.\n";

        return false;
    }
    */
}

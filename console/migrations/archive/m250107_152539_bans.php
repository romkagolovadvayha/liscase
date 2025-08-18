<?php

use console\components\migration\Migration;

/**
 * Class m250107_152539_bans
 */
class m250107_152539_bans extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','rust_app_id', self::INT_FIELD);

        $this->createTable('bans', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'reason' => 'VARCHAR(255) DEFAULT NULL',
            'banned_at' => self::TIMESTAMP_FIELD,
            'unbanned_at' => self::TIMESTAMP_FIELD,
            'ip'     => 'VARCHAR(120) DEFAULT NULL',
            'server_id'     => 'INT(11) DEFAULT NULL',
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('bans_user_id', 'bans', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('bans_server_id', 'bans', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250107_152539_bans cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250107_152539_bans cannot be reverted.\n";

        return false;
    }
    */
}

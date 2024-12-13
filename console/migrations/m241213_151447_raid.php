<?php

use console\components\migration\Migration;

/**
 * Class m241213_151447_raid
 */
class m241213_151447_raid extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_raid', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD_NOT_NULL,
            'location'   => self::VARCHAR_FIELD,
            'explosives' => 'VARCHAR(255) DEFAULT "[]"',
            'owners'     => 'JSON DEFAULT "[]"',
            'notify'     => self::TINYINT_1_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
            'server_id'     => 'INT(11) NOT NULL',
            'wipe'       => self::VARCHAR_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('user_raid_user_id', 'user_raid', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_raid_server_id', 'user_raid', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('user','raid_notify', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0');
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
        echo "m241213_151447_raid cannot be reverted.\n";

        return false;
    }
    */
}

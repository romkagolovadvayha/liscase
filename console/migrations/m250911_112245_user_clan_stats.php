<?php

use console\components\migration\Migration;

/**
 * Class m250911_112245_user_clan_stats
 */
class m250911_112245_user_clan_stats extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(
            'user_clan_stats',
            [
                'id'           => self::PRIMARY_KEY,
                'user_clan_id' => self::INT_FIELD,
                'steam_id'     => 'VARCHAR(19) DEFAULT NULL',
                'key'          => 'VARCHAR(60) DEFAULT NULL',
                'value'        => self::INT_FIELD,
                'server_id'    => self::INT_FIELD,
                'wipe'         => 'VARCHAR(30) DEFAULT NULL',
                'updated_at'   => self::TIMESTAMP_FIELD,
                'created_at'   => self::TIMESTAMP_FIELD,
            ],
            self::TABLE_OPTIONS
        );

        $this->addColumn('user_clan','status', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
        $this->addColumn('user_clan','leave_at', self::TIMESTAMP_FIELD);

        $this->addForeignKey('user_clan_stats_user_clan_id', 'user_clan_stats', 'user_clan_id',
                             'user_clan', 'id', 'CASCADE', 'CASCADE');

        $this->alterColumn('user_clan_stats', 'server_id', 'INT(11) DEFAULT NULL');
        $this->addForeignKey('user_clan_stats_server_id', 'user_clan_stats', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');


        $this->createTable(
            'clan_stats',
            [
                'id'           => self::PRIMARY_KEY,
                'clan_id'      => self::INT_FIELD,
                'raid_score'   => self::INT_FIELD,
                'kill_score'   => self::INT_FIELD,
                'server_id'    => 'INT(11) DEFAULT NULL',
                'wipe'         => 'VARCHAR(30) DEFAULT NULL',
                'updated_at'   => self::TIMESTAMP_FIELD,
                'created_at'   => self::TIMESTAMP_FIELD,
            ],
            self::TABLE_OPTIONS
        );

        $this->addForeignKey('clan_stats_clan_id', 'clan_stats', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_stats_server_id', 'clan_stats', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250911_112245_user_clan_stats cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250911_112245_user_clan_stats cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use console\components\migration\Migration;

/**
 * Class m241205_033023_currentTeam
 */
class m241205_033023_currentTeam extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('team', [
            'id'         => self::PRIMARY_KEY,
            'team_author_id'     => self::INT_FIELD_NOT_NULL,
            'server_id'     => 'INT(11) NOT NULL',
            'wipe'       => self::VARCHAR_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('user_team', [
            'id'         => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'team_id'     => self::INT_FIELD_NOT_NULL,
            'server_id'     => 'INT(11) NOT NULL',
            'wipe'       => self::VARCHAR_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('team_author_id', 'team', 'team_author_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('team_server_id', 'team', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_team_server_id', 'user_team', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_team_team_id', 'user_team', 'team_id',
                             'team', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_team_user_id', 'user_team', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241205_033023_currentTeam cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241205_033023_currentTeam cannot be reverted.\n";

        return false;
    }
    */
}

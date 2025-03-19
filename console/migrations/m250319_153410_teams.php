<?php

use console\components\migration\Migration;

/**
 * Class m250319_153410_teams
 */
class m250319_153410_teams extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('teams', [
            'id'             => self::PRIMARY_KEY,
            'leader_user_id' => self::INT_FIELD_NOT_NULL,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'server_id'      => 'INT(11) NOT NULL',
            'wipe'           => self::VARCHAR_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('teams_leader_user_id', 'teams', 'leader_user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('teams_user_id', 'teams', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('teams_server_id', 'teams', 'server_id',
                             'servers', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250319_153410_teams cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250319_153410_teams cannot be reverted.\n";

        return false;
    }
    */
}

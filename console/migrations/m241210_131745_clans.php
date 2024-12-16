<?php

use console\components\migration\Migration;

/**
 * Class m241210_131745_clans
 */
class m241210_131745_clans extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('clan', [
            'id'          => self::PRIMARY_KEY,
            'name'        => self::VARCHAR_FIELD,
            'description' => 'VARCHAR(500) DEFAULT NULL',
            'discord'     => self::VARCHAR_FIELD,
            'vk'          => self::VARCHAR_FIELD,
            'telegram'    => self::VARCHAR_FIELD,
            'recruitment' => self::TINYINT_FIELD,
            'user_id'     => self::INT_FIELD_NOT_NULL,
            'created_at'  => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('clan_application', [
            'id'          => self::PRIMARY_KEY,
            'user_id'     => self::INT_FIELD_NOT_NULL,
            'clan_id'     => self::INT_FIELD_NOT_NULL,
            'description' => 'VARCHAR(500) DEFAULT NULL',
            'status'      => self::TINYINT_FIELD,
            'created_at'  => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('user_clan', [
            'id'              => self::PRIMARY_KEY,
            'user_id'         => self::INT_FIELD_NOT_NULL,
            'clan_id'         => self::INT_FIELD_NOT_NULL,
            'invited_user_id' => self::INT_FIELD_NOT_NULL,
            'status'          => self::TINYINT_FIELD,
            'created_at'      => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('clan_user_id', 'clan', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('clan_application_clan_id', 'clan_application', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_application_user_id', 'clan_application', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('user_clan_clan_id', 'user_clan', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_clan_user_id', 'user_clan', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_clan_invited_user_id', 'user_clan', 'invited_user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241210_131745_clans cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241210_131745_clans cannot be reverted.\n";

        return false;
    }
    */
}

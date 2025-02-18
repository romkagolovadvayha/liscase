<?php

use console\components\migration\Migration;

/**
 * Class m250218_174555_clans
 */
class m250218_174555_clans extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('clan', [
            'id'                => self::PRIMARY_KEY,
            'title'             => self::VARCHAR_FIELD,
            'description_short' => 'VARCHAR(110) DEFAULT NULL',
            'description'       => 'TEXT DEFAULT NULL',
            'logo_image'        => self::VARCHAR_FIELD,
            'background_image'  => self::VARCHAR_FIELD,
            'user_id'           => self::INT_FIELD,
            'social_youtube'    => self::VARCHAR_FIELD,
            'social_discord'    => self::VARCHAR_FIELD,
            'social_vk'         => self::VARCHAR_FIELD,
            'social_twitch'     => self::VARCHAR_FIELD,
            'created_at'        => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('user_clan', [
            'id'             => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD,
            'clan_id'        => self::INT_FIELD,
            'clan_invite_id' => self::INT_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('clan_page', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD,
            'clan_id'    => self::INT_FIELD,
            'title'      => self::VARCHAR_FIELD,
            'text'       => 'TEXT DEFAULT NULL',
            'sort'       => self::INT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('clan_invite', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD,
            'clan_id'    => self::INT_FIELD,
            'hash'       => self::VARCHAR_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('user_role', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD,
            'clan_id'    => self::INT_FIELD,
            'role'       => self::TINYINT_1_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('clan_question', [
            'id'             => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD,
            'clan_id'        => self::INT_FIELD,
            'description'    => self::VARCHAR_FIELD,
            'social_youtube' => self::VARCHAR_FIELD,
            'social_discord' => self::VARCHAR_FIELD,
            'social_vk'      => self::VARCHAR_FIELD,
            'social_twitch'  => self::VARCHAR_FIELD,
            'status'         => self::TINYINT_1_FIELD,
            'created_at'     => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('clan_resource', [
            'id'         => self::PRIMARY_KEY,
            'user_id'    => self::INT_FIELD,
            'clan_id'    => self::INT_FIELD,
            'type'       => self::VARCHAR_FIELD,
            'src'        => self::VARCHAR_FIELD,
            'status'     => self::TINYINT_1_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('clan_user_id', 'clan', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_clan_user_id', 'user_clan', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_clan_clan_id', 'user_clan', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_clan_clan_invite_id', 'user_clan', 'clan_invite_id',
                             'clan_invite', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_question_user_id', 'clan_question', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_question_clan_id', 'clan_question', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_role_user_id', 'user_role', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('user_role_clan_id', 'user_role', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_invite_user_id', 'clan_invite', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_invite_clan_id', 'clan_invite', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_resource_user_id', 'clan_resource', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('clan_resource_clan_id', 'clan_resource', 'clan_id',
                             'clan', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250218_174555_clans cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250218_174555_clans cannot be reverted.\n";

        return false;
    }
    */
}

<?php

use yii\db\Migration;

/**
 * Class m251001_004822_add_invite_fields_to_clan_invite
 */
class m251001_004822_add_invite_fields_to_clan_invite extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Поля уже существуют, добавляем только индексы и внешний ключ если их нет
        try {
            $this->createIndex('clan_invite_invite_hash', 'clan_invite', 'invite_hash');
        } catch (\Exception $e) {
            // Индекс уже существует
        }
        
        try {
            $this->createIndex('clan_invite_status', 'clan_invite', 'status');
        } catch (\Exception $e) {
            // Индекс уже существует
        }
        
        try {
            $this->createIndex('user_clan_clan_invite_id', 'user_clan', 'clan_invite_id');
        } catch (\Exception $e) {
            // Индекс уже существует
        }
        
        try {
            $this->addForeignKey(
                'user_clan_clan_invite_id',
                'user_clan',
                'clan_invite_id',
                'clan_invite',
                'id',
                'SET NULL',
                'CASCADE'
            );
        } catch (\Exception $e) {
            // Внешний ключ уже существует
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем внешний ключ
        $this->dropForeignKey('user_clan_clan_invite_id', 'user_clan');
        
        // Удаляем индексы
        $this->dropIndex('user_clan_clan_invite_id', 'user_clan');
        $this->dropIndex('clan_invite_status', 'clan_invite');
        $this->dropIndex('clan_invite_invite_hash', 'clan_invite');
        
        // Удаляем поля
        $this->dropColumn('user_clan', 'clan_invite_id');
        $this->dropColumn('clan_invite', 'status');
        $this->dropColumn('clan_invite', 'invite_hash');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251001_004822_add_invite_fields_to_clan_invite cannot be reverted.\n";

        return false;
    }
    */
}

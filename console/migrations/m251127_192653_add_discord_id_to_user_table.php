<?php

use console\components\migration\Migration;

/**
 * Class m251127_192653_add_discord_id_to_user_table
 * Добавляет поле discord_id в таблицу user для хранения ID пользователя Discord
 */
class m251127_192653_add_discord_id_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'discord_id', $this->string()->null()->comment('Discord User ID'));
        $this->createIndex('idx_user_discord_id', '{{%user}}', 'discord_id', true); // Unique index
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_discord_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'discord_id');
    }
}

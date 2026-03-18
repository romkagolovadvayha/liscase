<?php

use console\components\migration\Migration;

/**
 * Добавляет поле twitch_id в таблицу user для привязки аккаунта Twitch
 */
class m260318_120000_add_twitch_id_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'twitch_id', $this->string()->null()->comment('Twitch User ID'));
        $this->createIndex('idx_user_twitch_id', '{{%user}}', 'twitch_id', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_twitch_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'twitch_id');
    }
}

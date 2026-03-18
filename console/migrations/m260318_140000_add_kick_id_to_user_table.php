<?php

use console\components\migration\Migration;

/**
 * Добавляет поле kick_id в таблицу user для привязки аккаунта Kick.com
 */
class m260318_140000_add_kick_id_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'kick_id', $this->string()->null()->comment('Kick.com User ID'));
        $this->createIndex('idx_user_kick_id', '{{%user}}', 'kick_id', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_kick_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'kick_id');
    }
}

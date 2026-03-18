<?php

use console\components\migration\Migration;

/**
 * Добавляет поле kick_link в user_profile для ссылки на канал Kick.com
 */
class m260318_160000_add_kick_link_to_user_profile extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user_profile}}', 'kick_link', $this->string(500)->null()->comment('Ссылка на Kick.com'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%user_profile}}', 'kick_link');
    }
}

<?php

use console\components\migration\Migration;

/**
 * Добавляет признак is_blogger в таблицу user (переключатель в бэкенде).
 */
class m260318_170000_add_is_blogger_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'is_blogger', $this->boolean()->notNull()->defaultValue(0)->comment('Блогер'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'is_blogger');
    }
}

<?php

use console\components\migration\Migration;

/**
 * Class m251227_000000_add_vk_id_to_user_table
 * Добавляет поле vk_id в таблицу user для хранения ID пользователя ВКонтакте
 */
class m251227_000000_add_vk_id_to_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'vk_id', $this->integer()->null()->comment('ID пользователя ВКонтакте'));
        $this->createIndex('idx_user_vk_id', 'user', 'vk_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_vk_id', 'user');
        $this->dropColumn('user', 'vk_id');
    }
}


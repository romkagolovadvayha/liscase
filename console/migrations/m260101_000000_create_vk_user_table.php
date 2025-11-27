<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `vk_user`.
 */
class m260101_000000_create_vk_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('vk_user', [
            'id' => self::PRIMARY_KEY,
            'vk_user_id' => 'INT(10) UNSIGNED NOT NULL COMMENT \'ID пользователя ВКонтакте\'',
            'first_name' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Имя\'',
            'last_name' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Фамилия\'',
            'screen_name' => 'VARCHAR(255) DEFAULT NULL COMMENT \'Screen name (username)\'',
            'can_send_message' => 'TINYINT(1) DEFAULT 0 COMMENT \'Можно отправлять сообщения (1-да, 0-нет)\'',
            'created_at' => 'DATETIME DEFAULT NULL COMMENT \'Дата создания\'',
            'updated_at' => 'DATETIME DEFAULT NULL COMMENT \'Дата обновления\'',
        ], self::TABLE_OPTIONS);

        $this->createIndex('idx-vk_user-vk_user_id', 'vk_user', 'vk_user_id', true);
        $this->createIndex('idx-vk_user-can_send_message', 'vk_user', 'can_send_message');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('vk_user');
    }
}


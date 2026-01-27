<?php

use console\components\migration\Migration;

/**
 * Class m251201_213406_create_user_vip_table
 * 
 * Создает таблицу для VIP подписок пользователей
 */
class m251201_213406_create_user_vip_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Создание таблицы user_vip
        $this->createTable('user_vip', [
            'id' => self::PRIMARY_KEY,
            'user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID пользователя\'',
            'expires_at' => 'INT(10) UNSIGNED NOT NULL COMMENT \'Дата окончания VIP (timestamp)\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS);

        // Внешний ключ на таблицу user
        $this->addForeignKey(
            'fk_user_vip_user_id',
            'user_vip',
            'user_id',
            'user',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Индексы для оптимизации запросов
        // Уникальный индекс для гарантии одной записи на пользователя
        $this->createIndex('idx_user_vip_user_id_unique', 'user_vip', 'user_id', true);
        
        // Индекс для поиска активных VIP (expires_at > текущее время)
        $this->createIndex('idx_user_vip_expires_at', 'user_vip', 'expires_at');
        
        // Составной индекс для быстрого поиска активного VIP пользователя
        $this->createIndex('idx_user_vip_user_expires', 'user_vip', ['user_id', 'expires_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем внешний ключ
        $this->dropForeignKey('fk_user_vip_user_id', 'user_vip');
        
        // Удаляем индексы
        $this->dropIndex('idx_user_vip_user_expires', 'user_vip');
        $this->dropIndex('idx_user_vip_expires_at', 'user_vip');
        $this->dropIndex('idx_user_vip_user_id_unique', 'user_vip');
        
        // Удаляем таблицу
        $this->dropTable('user_vip');
    }
}

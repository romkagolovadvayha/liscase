<?php

use console\components\migration\Migration;

/**
 * Class m251201_221008_add_unique_index_user_id_to_user_vip
 * 
 * Добавляет уникальный индекс на user_id, чтобы гарантировать одну запись на пользователя
 */
class m251201_221008_add_unique_index_user_id_to_user_vip extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Проверяем, существует ли таблица
        $tableSchema = $this->db->schema->getTableSchema('user_vip', true);
        if ($tableSchema === null) {
            echo "Таблица user_vip не существует. Пропускаем миграцию.\n";
            return;
        }

        // Удаляем старый не-уникальный индекс, если он существует
        try {
            $this->dropIndex('idx_user_vip_user_id', 'user_vip');
        } catch (\Exception $e) {
            // Индекс может не существовать, это нормально
        }

        // Удаляем составной индекс, который использует user_id
        try {
            $this->dropIndex('idx_user_vip_user_expires', 'user_vip');
        } catch (\Exception $e) {
            // Индекс может не существовать, это нормально
        }

        // Проверяем, существует ли уже уникальный индекс
        $uniqueIndexExists = $this->db->createCommand("SHOW INDEX FROM `user_vip` WHERE Key_name = 'idx_user_vip_user_id_unique'")->queryOne();
        if (!$uniqueIndexExists) {
            // Добавляем уникальный индекс на user_id
            $this->createIndex('idx_user_vip_user_id_unique', 'user_vip', 'user_id', true);
            echo "  > Created unique index 'idx_user_vip_user_id_unique' on table 'user_vip'\n";
        } else {
            echo "  > Unique index 'idx_user_vip_user_id_unique' already exists on table 'user_vip'\n";
        }

        // Восстанавливаем составной индекс для оптимизации запросов (если его нет)
        $compositeIndexExists = $this->db->createCommand("SHOW INDEX FROM `user_vip` WHERE Key_name = 'idx_user_vip_user_expires'")->queryOne();
        if (!$compositeIndexExists) {
            $this->createIndex('idx_user_vip_user_expires', 'user_vip', ['user_id', 'expires_at']);
            echo "  > Created index 'idx_user_vip_user_expires' on table 'user_vip'\n";
        } else {
            echo "  > Index 'idx_user_vip_user_expires' already exists on table 'user_vip'\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Проверяем, существует ли таблица
        $tableSchema = $this->db->schema->getTableSchema('user_vip', true);
        if ($tableSchema === null) {
            echo "Таблица user_vip не существует. Пропускаем откат миграции.\n";
            return;
        }

        // Удаляем уникальный индекс
        try {
            $this->dropIndex('idx_user_vip_user_expires', 'user_vip');
        } catch (\Exception $e) {
            // Индекс может не существовать
        }

        try {
            $this->dropIndex('idx_user_vip_user_id_unique', 'user_vip');
        } catch (\Exception $e) {
            // Индекс может не существовать
        }

        // Восстанавливаем обычный индекс
        $this->createIndex('idx_user_vip_user_id', 'user_vip', 'user_id');
        $this->createIndex('idx_user_vip_user_expires', 'user_vip', ['user_id', 'expires_at']);
    }
}

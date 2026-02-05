<?php

use console\components\migration\Migration;

/**
 * Добавляет индексы для оптимизации запросов в homepage-data endpoint
 */
class m260205_200000_add_homepage_data_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Индексы для таблицы task_v2 (активные задания)
        $this->createIndexIfNotExists(
            'idx_task_v2_is_active_sort',
            'task_v2',
            ['is_active', 'sort']
        );

        // Индексы для таблицы task_v2_user_completion (выполненные задания пользователя)
        $this->createIndexIfNotExists(
            'idx_task_v2_user_completion_user_id',
            'task_v2_user_completion',
            'user_id'
        );

        $this->createIndexIfNotExists(
            'idx_task_v2_user_completion_user_count',
            'task_v2_user_completion',
            ['user_id', 'count_completed']
        );

        // Индексы для таблицы servers (поиск серверов)
        $this->createIndexIfNotExists(
            'idx_servers_tag_status',
            'servers',
            ['tag', 'status']
        );

        $this->createIndexIfNotExists(
            'idx_servers_status_sort',
            'servers',
            ['status', 'sort']
        );

        // Дополнительный составной индекс для statistics (оптимизация getStats)
        // Используем SQL напрямую для экранирования зарезервированного слова `key`
        $indexExists = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'statistics' 
            AND index_name = 'idx_statistics_server_wipe_steam'
        ")->queryScalar();
        
        if (!$indexExists) {
            $this->execute("
                CREATE INDEX idx_statistics_server_wipe_steam 
                ON statistics (server_tag, wipe, steam_id)
            ");
        }

        // Индекс для statistics по steam_id и server_tag (для быстрого поиска статистики пользователя)
        $indexExists2 = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'statistics' 
            AND index_name = 'idx_statistics_steam_server'
        ")->queryScalar();
        
        if (!$indexExists2) {
            $this->execute("
                CREATE INDEX idx_statistics_steam_server 
                ON statistics (steam_id, server_tag, wipe)
            ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('idx_task_v2_is_active_sort', 'task_v2');
        $this->dropIndexIfExists('idx_task_v2_user_completion_user_id', 'task_v2_user_completion');
        $this->dropIndexIfExists('idx_task_v2_user_completion_user_count', 'task_v2_user_completion');
        $this->dropIndexIfExists('idx_servers_tag_status', 'servers');
        $this->dropIndexIfExists('idx_servers_status_sort', 'servers');
        
        // Удаляем индексы statistics через SQL
        try {
            $this->execute("DROP INDEX idx_statistics_server_wipe_steam ON statistics");
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
        
        try {
            $this->execute("DROP INDEX idx_statistics_steam_server ON statistics");
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
    }

    /**
     * Создает индекс, если он не существует
     */
    protected function createIndexIfNotExists($name, $table, $columns, $unique = false)
    {
        try {
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if (!$indexExists) {
                $this->createIndex($name, $table, $columns, $unique);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки, если индекс уже существует
        }
    }

    /**
     * Удаляет индекс, если он существует
     */
    protected function dropIndexIfExists($name, $table)
    {
        try {
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if ($indexExists) {
                $this->dropIndex($name, $table);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
    }
}


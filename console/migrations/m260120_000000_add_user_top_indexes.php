<?php

use console\components\migration\Migration;

/**
 * Добавляет индексы для оптимизации запросов к таблице user_top
 */
class m260120_000000_add_user_top_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Основной составной индекс для запроса топов
        // Используется для WHERE server_id = ? AND wipe = ? и ORDER BY value DESC в window function
        // Используем SQL напрямую для экранирования зарезервированного слова `key`
        $indexExists = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user_top' 
            AND index_name = 'idx_user_top_server_wipe_key_value'
        ")->queryScalar();
        
        if (!$indexExists) {
            $this->execute("
                CREATE INDEX idx_user_top_server_wipe_key_value 
                ON user_top (server_id, wipe, `key`, value)
            ");
        }

        // Индекс для JOIN с user
        $this->createIndexIfNotExists(
            'idx_user_top_user_id',
            'user_top',
            'user_id'
        );

        // Индекс для user.is_stats (для фильтрации)
        $this->createIndexIfNotExists(
            'idx_user_is_stats',
            'user',
            'is_stats'
        );

        // Составной индекс для user (id, is_stats) для оптимизации JOIN
        $this->createIndexIfNotExists(
            'idx_user_id_is_stats',
            'user',
            ['id', 'is_stats']
        );

        // Индекс для user_profile.user_id (для JOIN)
        $this->createIndexIfNotExists(
            'idx_user_profile_user_id',
            'user_profile',
            'user_id'
        );

        // Индексы для statistics (для запроса playtime)
        // Используем SQL напрямую для экранирования зарезервированного слова `key`
        $indexExistsStats = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'statistics' 
            AND index_name = 'idx_statistics_server_wipe_key_value'
        ")->queryScalar();
        
        if (!$indexExistsStats) {
            $this->execute("
                CREATE INDEX idx_statistics_server_wipe_key_value 
                ON statistics (server_tag, wipe, `key`, value)
            ");
        }

        // Индекс для statistics.steam_id (для JOIN с user)
        $this->createIndexIfNotExists(
            'idx_statistics_steam_id',
            'statistics',
            'steam_id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('idx_user_top_server_wipe_key_value', 'user_top');
        $this->dropIndexIfExists('idx_user_top_user_id', 'user_top');
        $this->dropIndexIfExists('idx_user_is_stats', 'user');
        $this->dropIndexIfExists('idx_user_id_is_stats', 'user');
        $this->dropIndexIfExists('idx_user_profile_user_id', 'user_profile');
        $this->dropIndexIfExists('idx_statistics_server_wipe_key_value', 'statistics');
        $this->dropIndexIfExists('idx_statistics_steam_id', 'statistics');
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


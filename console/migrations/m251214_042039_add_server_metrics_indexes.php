<?php

use yii\db\Migration;

/**
 * Добавляет индексы для оптимизации запросов в getServerMetrics
 */
class m251214_042039_add_server_metrics_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Индексы для таблицы statistics
        // Составной индекс для запросов WHERE server_tag = X AND key IN (...) GROUP BY steam_id
        // Это критично важно для оптимизации запросов с фильтрацией по server_tag и key
        $this->createIndexIfNotExists(
            'idx_statistics_server_tag_key',
            'statistics',
            ['server_tag', 'key']
        );
        
        // Составной индекс для запросов WHERE server_tag = X AND key = Y GROUP BY steam_id
        // Добавляем steam_id для покрывающего индекса
        $this->createIndexIfNotExists(
            'idx_statistics_server_tag_key_steam',
            'statistics',
            ['server_tag', 'key', 'steam_id']
        );
        
        // Индексы для таблицы statistics_kills
        // Составной индекс для запросов WHERE server_tag = X AND type = Y GROUP BY steam_id
        $this->createIndexIfNotExists(
            'idx_statistics_kills_server_tag_type',
            'statistics_kills',
            ['server_tag', 'type']
        );
        
        // Составной индекс для запросов с distance и type
        $this->createIndexIfNotExists(
            'idx_statistics_kills_server_tag_type_distance',
            'statistics_kills',
            ['server_tag', 'type', 'distance']
        );
        
        // Индекс для таблицы servers_reports
        // Для запросов WHERE server_tag = X GROUP BY steam_id
        $this->createIndexIfNotExists(
            'idx_servers_reports_server_tag',
            'servers_reports',
            'server_tag'
        );
        
        // Составной индекс для запросов WHERE server_tag = X GROUP BY recepient_steam_id
        $this->createIndexIfNotExists(
            'idx_servers_reports_server_tag_recepient',
            'servers_reports',
            ['server_tag', 'recepient_steam_id']
        );
        
        // Индекс для таблицы user_raid
        // Для запросов WHERE server_id = X AND type = Y GROUP BY user_id
        $this->createIndexIfNotExists(
            'idx_user_raid_server_id_type',
            'user_raid',
            ['server_id', 'type']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('idx_user_raid_server_id_type', 'user_raid');
        $this->dropIndexIfExists('idx_servers_reports_server_tag_recepient', 'servers_reports');
        $this->dropIndexIfExists('idx_servers_reports_server_tag', 'servers_reports');
        $this->dropIndexIfExists('idx_statistics_kills_server_tag_type_distance', 'statistics_kills');
        $this->dropIndexIfExists('idx_statistics_kills_server_tag_type', 'statistics_kills');
        $this->dropIndexIfExists('idx_statistics_server_tag_key_steam', 'statistics');
        $this->dropIndexIfExists('idx_statistics_server_tag_key', 'statistics');
    }
    
    /**
     * Создает индекс, если он еще не существует
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     * @param string|array $columns Колонки для индекса
     * @param bool $unique Уникальный индекс
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
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
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


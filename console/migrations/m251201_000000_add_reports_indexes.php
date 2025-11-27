<?php

use yii\db\Migration;

/**
 * Добавляет индексы для оптимизации запросов в ReportsController
 */
class m251201_000000_add_reports_indexes extends Migration
{
    public function safeUp()
    {
        // Индексы для таблицы servers_reports
        $this->createIndex(
            'idx_servers_reports_wipe_tag',
            'servers_reports',
            ['wipe', 'server_tag']
        );
        
        $this->createIndex(
            'idx_servers_reports_recepient',
            'servers_reports',
            'recepient_steam_id'
        );
        
        $this->createIndex(
            'idx_servers_reports_created',
            'servers_reports',
            'created_at'
        );
        
        // Составной индекс для оптимизации основного запроса
        $this->createIndex(
            'idx_servers_reports_composite',
            'servers_reports',
            ['wipe', 'server_tag', 'recepient_steam_id', 'created_at']
        );
        
        // Индексы для таблицы user_checking
        $this->createIndex(
            'idx_user_checking_user_created',
            'user_checking',
            ['user_id', 'created_at']
        );
        
        // Индекс для таблицы user (если еще нет)
        $indexExists = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user' 
            AND index_name = 'idx_user_steam_status'
        ")->queryScalar();
        
        if (!$indexExists) {
            $this->createIndex(
                'idx_user_steam_status',
                'user',
                ['steam_id', 'status', 'unbanned_at']
            );
        }
    }

    public function safeDown()
    {
        $this->dropIndex('idx_servers_reports_composite', 'servers_reports');
        $this->dropIndex('idx_servers_reports_created', 'servers_reports');
        $this->dropIndex('idx_servers_reports_recepient', 'servers_reports');
        $this->dropIndex('idx_servers_reports_wipe_tag', 'servers_reports');
        $this->dropIndex('idx_user_checking_user_created', 'user_checking');
        
        $indexExists = $this->db->createCommand("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user' 
            AND index_name = 'idx_user_steam_status'
        ")->queryScalar();
        
        if ($indexExists) {
            $this->dropIndex('idx_user_steam_status', 'user');
        }
    }
}


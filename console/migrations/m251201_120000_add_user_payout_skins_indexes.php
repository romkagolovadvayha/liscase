<?php

use console\components\migration\Migration;

/**
 * Class m251201_120000_add_user_payout_skins_indexes
 * Добавляет индексы для оптимизации запросов к таблице user_payout_skins
 */
class m251201_120000_add_user_payout_skins_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Индекс для фильтрации по статусу
        $this->createIndex('idx_user_payout_skins_status', 'user_payout_skins', 'status');
        
        // Индекс для сортировки и группировки по дате
        $this->createIndex('idx_user_payout_skins_created', 'user_payout_skins', 'created_at');
        
        // Составной индекс для частых запросов по статусу и дате
        $this->createIndex('idx_user_payout_skins_status_created', 'user_payout_skins', ['status', 'created_at']);
        
        // Составной индекс для поиска по пользователю и статусу
        $this->createIndex('idx_user_payout_skins_user_status', 'user_payout_skins', ['user_id', 'status']);
        
        // Составной индекс для группировки по дате и статусу
        $this->createIndex('idx_user_payout_skins_created_status', 'user_payout_skins', ['created_at', 'status']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx_user_payout_skins_created_status', 'user_payout_skins');
        $this->dropIndex('idx_user_payout_skins_user_status', 'user_payout_skins');
        $this->dropIndex('idx_user_payout_skins_status_created', 'user_payout_skins');
        $this->dropIndex('idx_user_payout_skins_created', 'user_payout_skins');
        $this->dropIndex('idx_user_payout_skins_status', 'user_payout_skins');
    }
}


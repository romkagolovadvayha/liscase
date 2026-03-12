<?php

use console\components\migration\Migration;

/**
 * Handles adding game_type column to table `market_skins`.
 */
class m260115_130000_add_game_type_to_market_skins extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем поле game_type (rust/cs2)
        $this->addColumn('market_skins', 'game_type', "ENUM('rust', 'cs2') NOT NULL DEFAULT 'rust' COMMENT 'Тип игры: rust или cs2'");
        
        // Удаляем старый уникальный индекс
        $this->dropIndex('idx-market_skins-unique_class_instance', 'market_skins');
        
        // Создаем новый уникальный индекс с game_type
        $this->createIndex('idx-market_skins-unique_class_instance_game', 'market_skins', ['class_id', 'instance_id', 'game_type'], true);
        
        // Добавляем индекс для фильтрации по game_type
        $this->createIndex('idx-market_skins-game_type', 'market_skins', 'game_type');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем новый индекс
        $this->dropIndex('idx-market_skins-unique_class_instance_game', 'market_skins');
        $this->dropIndex('idx-market_skins-game_type', 'market_skins');
        
        // Восстанавливаем старый уникальный индекс
        $this->createIndex('idx-market_skins-unique_class_instance', 'market_skins', ['class_id', 'instance_id'], true);
        
        // Удаляем поле game_type
        $this->dropColumn('market_skins', 'game_type');
    }
}














